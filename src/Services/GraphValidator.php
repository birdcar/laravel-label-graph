<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Services;

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRoute;
use Illuminate\Support\Collection;

class GraphValidator
{
    public function __construct(
        protected RouteGenerator $routeGenerator
    ) {}

    /**
     * Validate graph integrity and return issues.
     *
     * @return Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}>
     */
    public function validate(): Collection
    {
        /** @var Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}> $issues */
        $issues = collect();

        $this->checkOrphanedRoutes($issues);
        $this->checkMissingLabels($issues);
        $this->checkDepthMismatches($issues);

        return $issues;
    }

    /**
     * @param  Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}>  $issues
     */
    protected function checkOrphanedRoutes(Collection &$issues): void
    {
        $validPaths = $this->computeValidPaths();

        $orphaned = LabelRoute::whereNotIn('path', $validPaths)->get();

        foreach ($orphaned as $route) {
            $issues->push([
                'severity' => 'warning',
                'type' => 'orphaned_route',
                'message' => "Orphaned route: {$route->path}",
                'fix' => 'Run label-graph:route:prune to remove',
                'route_id' => $route->id,
            ]);
        }
    }

    /**
     * @param  Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}>  $issues
     */
    protected function checkMissingLabels(Collection &$issues): void
    {
        /** @var Collection<string, int> $labelSlugs */
        $labelSlugs = Label::pluck('slug')->flip();

        foreach (LabelRoute::all() as $route) {
            foreach ($route->segments as $segment) {
                if (! isset($labelSlugs[$segment])) {
                    $issues->push([
                        'severity' => 'error',
                        'type' => 'missing_label',
                        'message' => "Route {$route->path} references non-existent label: {$segment}",
                        'fix' => 'Create the missing label or regenerate routes',
                    ]);
                }
            }
        }
    }

    /**
     * @param  Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}>  $issues
     */
    protected function checkDepthMismatches(Collection &$issues): void
    {
        foreach (LabelRoute::all() as $route) {
            $expectedDepth = count($route->segments) - 1;
            if ($route->depth !== $expectedDepth) {
                $issues->push([
                    'severity' => 'warning',
                    'type' => 'depth_mismatch',
                    'message' => "Route {$route->path} has depth {$route->depth}, expected {$expectedDepth}",
                    'fix' => 'Will be corrected automatically',
                    'route_id' => $route->id,
                    'expected_depth' => $expectedDepth,
                ]);
            }
        }
    }

    /**
     * Auto-fix safe issues.
     *
     * @param  Collection<int, array{severity: string, type: string, message: string, fix?: string, route_id?: string, expected_depth?: int}>  $issues
     */
    public function autoFix(Collection $issues): int
    {
        $fixed = 0;

        foreach ($issues as $issue) {
            if ($issue['type'] === 'depth_mismatch' && isset($issue['route_id'], $issue['expected_depth'])) {
                LabelRoute::where('id', $issue['route_id'])
                    ->update(['depth' => $issue['expected_depth']]);
                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * Compute all valid paths from the current relationship graph.
     *
     * @return array<int, string>
     */
    public function computeValidPaths(): array
    {
        // Re-use route generator logic to compute what paths SHOULD exist
        $labels = Label::all()->keyBy('id');
        $adjacency = $this->buildAdjacencyList();

        /** @var array<int, string> $paths */
        $paths = [];

        foreach ($labels->keys() as $labelId) {
            $this->generatePathsFrom((string) $labelId, [(string) $labelId], $adjacency, $labels, $paths);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function buildAdjacencyList(): array
    {
        /** @var array<string, array<int, string>> $adjacency */
        $adjacency = [];

        foreach (\Birdcar\LabelGraph\Models\LabelRelationship::all() as $rel) {
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
        $pathSlugs = array_map(
            function (string $id) use ($labels): string {
                /** @var Label $label */
                $label = $labels[$id];

                return $label->slug;
            },
            $currentPath
        );
        $paths[] = implode('.', $pathSlugs);

        $children = $adjacency[$currentId] ?? [];

        foreach ($children as $childId) {
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
}
