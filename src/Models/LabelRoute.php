<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Models;

use Birdcar\LabelTree\Exceptions\InvalidRouteException;
use Birdcar\LabelTree\Query\PathQueryAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $path
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array<int, string> $segments
 *
 * @method static Builder<static> wherePathMatches(string $pattern)
 * @method static Builder<static> wherePathLike(string $pattern)
 * @method static Builder<static> whereAncestorOf(string $path)
 * @method static Builder<static> whereDescendantOf(string $path)
 * @method static Builder<static> whereDepth(int $depth)
 * @method static Builder<static> whereDepthBetween(int $min, int $max)
 * @method static Builder<static> whereDepthLte(int $max)
 * @method static Builder<static> whereDepthGte(int $min)
 */
class LabelRoute extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'path',
        'depth',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
        ];
    }

    /**
     * Get the labels in this route's path.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Label>
     */
    public function labels(): \Illuminate\Database\Eloquent\Collection
    {
        $slugs = $this->segments;

        return Label::whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (Label $label): int|false => array_search($label->slug, $slugs, true));
    }

    /**
     * Get path segments as array.
     *
     * @return array<int, string>
     */
    public function getSegmentsAttribute(): array
    {
        return explode('.', $this->path);
    }

    public function getTable(): string
    {
        return config('label-tree.tables.routes', 'label_routes');
    }

    // Query scopes

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePathMatches(Builder $query, string $pattern): Builder
    {
        return $this->getAdapter()->wherePathMatches($query, 'path', $pattern);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePathLike(Builder $query, string $pattern): Builder
    {
        return $this->getAdapter()->wherePathLike($query, 'path', $pattern);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereAncestorOf(Builder $query, string $path): Builder
    {
        return $this->getAdapter()->whereAncestorOf($query, 'path', $path);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDescendantOf(Builder $query, string $path): Builder
    {
        return $this->getAdapter()->whereDescendantOf($query, 'path', $path);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDepth(Builder $query, int $depth): Builder
    {
        return $query->where('depth', $depth);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDepthBetween(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween('depth', [$min, $max]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDepthLte(Builder $query, int $max): Builder
    {
        return $query->where('depth', '<=', $max);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereDepthGte(Builder $query, int $min): Builder
    {
        return $query->where('depth', '>=', $min);
    }

    // Instance methods

    /**
     * Get all ancestors of this route.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LabelRoute>
     */
    public function ancestors(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereAncestorOf($this->path)->get();
    }

    /**
     * Get all descendants of this route.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LabelRoute>
     */
    public function descendants(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereDescendantOf($this->path)->get();
    }

    /**
     * Get the parent route.
     */
    public function parent(): ?LabelRoute
    {
        $segments = $this->segments;
        if (count($segments) <= 1) {
            return null;
        }

        array_pop($segments);
        $parentPath = implode('.', $segments);

        return static::where('path', $parentPath)->first();
    }

    /**
     * Get direct children of this route.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LabelRoute>
     */
    public function children(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereDescendantOf($this->path)
            ->where('depth', $this->depth + 1)
            ->get();
    }

    /**
     * Check if this route is an ancestor of another.
     */
    public function isAncestorOf(LabelRoute|string $other): bool
    {
        $otherPath = $other instanceof LabelRoute ? $other->path : $other;

        return str_starts_with($otherPath, $this->path.'.');
    }

    /**
     * Check if this route is a descendant of another.
     */
    public function isDescendantOf(LabelRoute|string $other): bool
    {
        $otherPath = $other instanceof LabelRoute ? $other->path : $other;

        return str_starts_with($this->path, $otherPath.'.');
    }

    /**
     * Check if this route is a root (depth 0).
     */
    public function isRoot(): bool
    {
        return $this->depth === 0;
    }

    /**
     * Check if this route has no children.
     */
    public function isLeaf(): bool
    {
        return $this->children()->isEmpty();
    }

    protected function getAdapter(): PathQueryAdapter
    {
        return app(PathQueryAdapter::class);
    }

    /**
     * Get all labelable models of a specific type attached to this route.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return MorphToMany<TModel, $this>
     */
    public function labelables(string $modelClass): MorphToMany
    {
        return $this->morphedByMany(
            $modelClass,
            'labelable',
            config('label-tree.tables.labelables', 'labelables'),
            'label_route_id',
            'labelable_id'
        );
    }

    /**
     * Get all attachments grouped by type.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Model>>
     */
    public function allLabelables(): Collection
    {
        $table = config('label-tree.tables.labelables', 'labelables');

        /** @var Collection<int, string> $types */
        $types = DB::table($table)
            ->where('label_route_id', $this->id)
            ->distinct()
            ->pluck('labelable_type');

        return $types->mapWithKeys(function (string $type): array {
            /** @var class-string<Model> $type */
            return [$type => $this->labelables($type)->get()];
        });
    }

    /**
     * Check if any models are attached to this route.
     */
    public function hasAttachments(): bool
    {
        $table = config('label-tree.tables.labelables', 'labelables');

        return DB::table($table)
            ->where('label_route_id', $this->id)
            ->exists();
    }

    /**
     * Get count of all attachments.
     */
    public function attachmentCount(): int
    {
        $table = config('label-tree.tables.labelables', 'labelables');

        return DB::table($table)
            ->where('label_route_id', $this->id)
            ->count();
    }

    /**
     * Migrate all attachments from one route to another.
     */
    public static function migrateAttachments(string $fromPath, string $toPath): int
    {
        $fromRoute = static::where('path', $fromPath)->first();
        $toRoute = static::where('path', $toPath)->first();

        if (! $fromRoute || ! $toRoute) {
            throw new InvalidRouteException('Source or target route not found');
        }

        $table = config('label-tree.tables.labelables', 'labelables');

        return DB::table($table)
            ->where('label_route_id', $fromRoute->id)
            ->update(['label_route_id' => $toRoute->id]);
    }
}
