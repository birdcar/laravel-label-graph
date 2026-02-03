<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Models;

use Birdcar\LabelTree\Exceptions\InvalidRouteException;
use Birdcar\LabelTree\Ltree\LtreeExpression;
use Birdcar\LabelTree\Query\PathQueryAdapter;
use Birdcar\LabelTree\Query\PostgresAdapter;
use Birdcar\LabelTree\Query\SqliteAdapter;
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
 * @method static Builder<static> wherePathMatchesText(string $pattern)
 * @method static Builder<static> wherePathLike(string $pattern)
 * @method static Builder<static> whereAncestorOf(string $path)
 * @method static Builder<static> whereDescendantOf(string $path)
 * @method static Builder<static> whereDepth(int $depth)
 * @method static Builder<static> whereDepthBetween(int $min, int $max)
 * @method static Builder<static> whereDepthLte(int $max)
 * @method static Builder<static> whereDepthGte(int $min)
 * @method static Builder<static> whereNlevel(int $level)
 * @method static Builder<static> selectNlevel(string $alias = 'level')
 * @method static Builder<static> selectSubpath(int $offset, ?int $len = null, string $alias = 'subpath')
 * @method static Builder<static> whereSubpathEquals(int $offset, ?int $len, string $value)
 * @method static Builder<static> wherePathInAncestors(array<int, string> $paths)
 * @method static Builder<static> wherePathInDescendants(array<int, string> $paths)
 * @method static Builder<static> selectConcat(string $column1, string $column2, string $alias = 'concat_path')
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
    public function scopeWherePathMatchesText(Builder $query, string $pattern): Builder
    {
        return $this->getAdapter()->wherePathMatchesText($query, 'path', $pattern);
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
     * Get the expression builder for ltree functions.
     */
    protected function getExpressionBuilder(): LtreeExpression
    {
        $adapter = $this->getAdapter();
        $driver = DB::connection()->getDriverName();
        $hasLtree = $adapter instanceof PostgresAdapter && $adapter->hasLtreeSupport();

        return new LtreeExpression($driver, $hasLtree);
    }

    /**
     * Ensure SQLite ltree functions are registered.
     *
     * @param  Builder<static>  $query
     */
    protected function ensureLtreeFunctions(Builder $query): void
    {
        $adapter = $this->getAdapter();
        if ($adapter instanceof SqliteAdapter) {
            $adapter->ensureLtreeFunctions($query);
        }
    }

    // Ltree function scopes

    /**
     * Add nlevel to select.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSelectNlevel(Builder $query, string $alias = 'level'): Builder
    {
        $this->ensureLtreeFunctions($query);
        $expr = $this->getExpressionBuilder()->nlevel('path');

        return $query->addSelect(DB::raw("{$expr->getValue(DB::connection()->getQueryGrammar())} as {$alias}"));
    }

    /**
     * Add subpath to select.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSelectSubpath(
        Builder $query,
        int $offset,
        ?int $len = null,
        string $alias = 'subpath'
    ): Builder {
        $this->ensureLtreeFunctions($query);
        $expr = $this->getExpressionBuilder()->subpath('path', $offset, $len);

        return $query->addSelect(DB::raw("{$expr->getValue(DB::connection()->getQueryGrammar())} as {$alias}"));
    }

    /**
     * Filter by nlevel (alias for whereDepth using nlevel semantics).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereNlevel(Builder $query, int $level): Builder
    {
        // nlevel counts labels (depth + 1 for root = 0)
        return $query->where('depth', $level - 1);
    }

    /**
     * Filter by subpath value.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereSubpathEquals(
        Builder $query,
        int $offset,
        ?int $len,
        string $value
    ): Builder {
        $this->ensureLtreeFunctions($query);
        $expr = $this->getExpressionBuilder()->subpath('path', $offset, $len);

        return $query->whereRaw("{$expr->getValue(DB::connection()->getQueryGrammar())} = ?", [$value]);
    }

    // Array operator scopes (PostgreSQL with ltree only)

    /**
     * Filter to paths that have an ancestor in the given array.
     *
     * @param  Builder<static>  $query
     * @param  array<int, string>  $paths
     * @return Builder<static>
     */
    public function scopeWherePathInAncestors(Builder $query, array $paths): Builder
    {
        return $this->getAdapter()->wherePathHasAncestorIn($query, 'path', $paths);
    }

    /**
     * Filter to paths that have a descendant in the given array.
     *
     * @param  Builder<static>  $query
     * @param  array<int, string>  $paths
     * @return Builder<static>
     */
    public function scopeWherePathInDescendants(Builder $query, array $paths): Builder
    {
        return $this->getAdapter()->wherePathHasDescendantIn($query, 'path', $paths);
    }

    /**
     * Check if array operators are supported.
     */
    public static function supportsArrayOperators(): bool
    {
        return app(PathQueryAdapter::class)->supportsArrayOperators();
    }

    /**
     * Find first ancestor from candidates.
     *
     * @param  array<int, string>  $candidates
     */
    public static function firstAncestorFrom(string $path, array $candidates): ?string
    {
        return app(PathQueryAdapter::class)->firstAncestorFrom($path, $candidates);
    }

    /**
     * Find first descendant from candidates.
     *
     * @param  array<int, string>  $candidates
     */
    public static function firstDescendantFrom(string $path, array $candidates): ?string
    {
        return app(PathQueryAdapter::class)->firstDescendantFrom($path, $candidates);
    }

    // Concat scope

    /**
     * Add concatenated path to select.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSelectConcat(
        Builder $query,
        string $column1,
        string $column2,
        string $alias = 'concat_path'
    ): Builder {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'pgsql' => "({$column1} || '.' || {$column2})",
            'mysql' => "CONCAT({$column1}, '.', {$column2})",
            'sqlite' => "({$column1} || '.' || {$column2})",
            default => "CONCAT({$column1}, '.', {$column2})",
        };

        return $query->addSelect(DB::raw("{$expr} as {$alias}"));
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
