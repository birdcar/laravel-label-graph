<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Services;

use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RouteGenerator
{
    /**
     * Regenerate all routes from the relationship graph.
     *
     * @return Collection<int, LabelRoute>
     */
    public function generateAll(): Collection
    {
        return DB::transaction(function (): Collection {
            // Get all labels
            /** @var Collection<string, Label> $labels */
            $labels = Label::all()->keyBy('id');

            // Get all relationships as adjacency list
            $adjacency = $this->buildAdjacencyList();

            // Generate all paths starting from each label
            /** @var array<int, string> $paths */
            $paths = [];

            foreach ($labels->keys() as $labelId) {
                $this->generatePathsFrom((string) $labelId, [(string) $labelId], $adjacency, $labels, $paths);
            }

            // Sync routes table
            return $this->syncRoutes($paths);
        });
    }

    /**
     * Generate routes incrementally for a new relationship.
     */
    public function generateForRelationship(LabelRelationship $relationship): void
    {
        DB::transaction(function (): void {
            $this->generateAll(); // Simple approach for v1
        });
    }

    /**
     * Remove routes that are no longer valid after relationship deletion.
     */
    public function pruneForDeletedRelationship(LabelRelationship $relationship): void
    {
        DB::transaction(function (): void {
            $this->generateAll(); // Regenerate and sync handles pruning
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function buildAdjacencyList(): array
    {
        /** @var array<string, array<int, string>> $adjacency */
        $adjacency = [];

        /** @var LabelRelationship $rel */
        foreach (LabelRelationship::all() as $rel) {
            if (! isset($adjacency[$rel->parent_label_id])) {
                $adjacency[$rel->parent_label_id] = [];
            }
            $adjacency[$rel->parent_label_id][] = $rel->child_label_id;
        }

        return $adjacency;
    }

    /**
     * @param  array<int, string>  $currentPath
     * @param  array<string, array<int, string>>  $adjacency
     * @param  Collection<string, Label>  $labels
     * @param  array<int, string>  $paths
     */
    protected function generatePathsFrom(
        string $currentId,
        array $currentPath,
        array $adjacency,
        Collection $labels,
        array &$paths
    ): void {
        // Add current path as a valid route
        $pathSlugs = array_map(
            function (string $id) use ($labels): string {
                /** @var Label $label */
                $label = $labels[$id];

                return $label->slug;
            },
            $currentPath
        );
        $paths[] = implode('.', $pathSlugs);

        // Recurse to children
        $children = $adjacency[$currentId] ?? [];

        foreach ($children as $childId) {
            // Avoid cycles (shouldn't happen, but safety)
            if (! in_array($childId, $currentPath, true)) {
                $this->generatePathsFrom(
                    $childId,
                    [...$currentPath, $childId],
                    $adjacency,
                    $labels,
                    $paths
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $paths
     * @return Collection<int, LabelRoute>
     */
    protected function syncRoutes(array $paths): Collection
    {
        $uniquePaths = collect($paths)->unique()->values();

        // Get existing routes
        $existing = LabelRoute::pluck('path', 'id');

        // Determine adds and deletes
        $toAdd = $uniquePaths->diff($existing->values());
        $toDelete = $existing->filter(fn (string $path): bool => ! $uniquePaths->contains($path));

        // Delete old routes
        LabelRoute::whereIn('id', $toDelete->keys())->delete();

        // Create new routes
        /** @var Collection<int, LabelRoute> $created */
        $created = $toAdd->map(fn (string $path): LabelRoute => LabelRoute::create([
            'path' => $path,
            'depth' => substr_count($path, '.'),
        ]));

        return $created;
    }
}
