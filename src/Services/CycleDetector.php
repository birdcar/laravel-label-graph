<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Services;

use Birdcar\LabelGraph\Models\LabelRelationship;

class CycleDetector
{
    /**
     * Check if adding this relationship would create a cycle.
     *
     * A cycle exists if we can reach the parent from the child
     * by following existing relationships.
     */
    public function wouldCreateCycle(LabelRelationship $proposed): bool
    {
        $parentId = $proposed->parent_label_id;
        $childId = $proposed->child_label_id;

        // If we can reach parent from child, adding parent->child creates cycle
        return $this->canReach($childId, $parentId);
    }

    /**
     * DFS to check if 'target' is reachable from 'start' following edges.
     */
    protected function canReach(string $startId, string $targetId): bool
    {
        /** @var array<string, bool> $visited */
        $visited = [];

        /** @var array<int, string> $stack */
        $stack = [$startId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $targetId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            // Get all children of current node
            $children = LabelRelationship::where('parent_label_id', $current)
                ->pluck('child_label_id');

            foreach ($children as $childId) {
                $stack[] = $childId;
            }
        }

        return false;
    }
}
