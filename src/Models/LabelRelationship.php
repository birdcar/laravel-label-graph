<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Models;

use Birdcar\LabelGraph\Exceptions\CycleDetectedException;
use Birdcar\LabelGraph\Exceptions\InvalidRouteException;
use Birdcar\LabelGraph\Exceptions\RoutesInUseException;
use Birdcar\LabelGraph\Exceptions\SelfReferenceException;
use Birdcar\LabelGraph\Services\CycleDetector;
use Birdcar\LabelGraph\Services\RouteGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $parent_label_id
 * @property string $child_label_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LabelRelationship extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'parent_label_id',
        'child_label_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabelRelationship $relationship): void {
            // Self-reference check
            if ($relationship->parent_label_id === $relationship->child_label_id) {
                throw new SelfReferenceException(
                    'Cannot create self-referential relationship'
                );
            }

            // Cycle detection
            /** @var CycleDetector $detector */
            $detector = app(CycleDetector::class);
            if ($detector->wouldCreateCycle($relationship)) {
                throw new CycleDetectedException(
                    'Creating this relationship would form a cycle'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Label::class, 'parent_label_id');
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Label::class, 'child_label_id');
    }

    public function getTable(): string
    {
        return config('label-graph.tables.relationships', 'label_relationships');
    }

    /**
     * Override delete to block if routes have attachments.
     */
    public function delete(): ?bool
    {
        if (! $this->canDelete()) {
            $count = $this->getAffectedAttachmentCount();
            $routes = $this->getAffectedRoutes()->pluck('path')->implode(', ');

            throw new RoutesInUseException(
                "Cannot delete relationship: {$count} attachments exist on routes: {$routes}"
            );
        }

        return $this->performDelete();
    }

    /**
     * Check if relationship can be safely deleted.
     */
    public function canDelete(): bool
    {
        return $this->getAffectedAttachmentCount() === 0;
    }

    /**
     * Get routes that would be orphaned by deleting this relationship.
     *
     * @return Collection<int, LabelRoute>
     */
    public function getAffectedRoutes(): Collection
    {
        /** @var RouteGenerator $generator */
        $generator = app(RouteGenerator::class);

        return $generator->getRoutesAffectedByDeletion($this);
    }

    /**
     * Get total attachments on affected routes.
     */
    public function getAffectedAttachmentCount(): int
    {
        $routeIds = $this->getAffectedRoutes()->pluck('id');

        if ($routeIds->isEmpty()) {
            return 0;
        }

        $table = config('label-graph.tables.labelables', 'labelables');

        return DB::table($table)
            ->whereIn('label_route_id', $routeIds)
            ->count();
    }

    /**
     * Delete relationship and cascade: remove routes AND attachments.
     */
    public function deleteAndCascade(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $routeIds = $this->getAffectedRoutes()->pluck('id');

            // Delete attachments first
            $table = config('label-graph.tables.labelables', 'labelables');
            DB::table($table)->whereIn('label_route_id', $routeIds)->delete();

            // Now safe to delete
            return $this->performDelete();
        });
    }

    /**
     * Migrate attachments to replacement route, then delete.
     */
    public function deleteAndReplace(string $replacementPath): ?bool
    {
        $replacement = LabelRoute::where('path', $replacementPath)->first();

        if (! $replacement) {
            throw new InvalidRouteException("Replacement route not found: {$replacementPath}");
        }

        return DB::transaction(function () use ($replacement): ?bool {
            $routeIds = $this->getAffectedRoutes()->pluck('id');

            // Migrate attachments
            $table = config('label-graph.tables.labelables', 'labelables');
            DB::table($table)
                ->whereIn('label_route_id', $routeIds)
                ->update(['label_route_id' => $replacement->id]);

            // Now safe to delete
            return $this->performDelete();
        });
    }

    /**
     * Force delete without attachment checking (for cascade scenarios).
     */
    public function forceDelete(): ?bool
    {
        return $this->performDelete();
    }

    /**
     * Internal delete that triggers route regeneration.
     */
    protected function performDelete(): ?bool
    {
        $result = parent::delete();

        if ($result) {
            /** @var RouteGenerator $generator */
            $generator = app(RouteGenerator::class);
            $generator->pruneForDeletedRelationship($this);
        }

        return $result;
    }
}
