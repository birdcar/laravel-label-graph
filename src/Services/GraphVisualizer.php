<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Services;

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Illuminate\Support\Collection;

class GraphVisualizer
{
    /**
     * Render the label graph as an indented tree.
     */
    public function renderTree(bool $includeRoutes = false): string
    {
        /** @var Collection<string, Label> $labels */
        $labels = Label::all()->keyBy('id');
        $relationships = LabelRelationship::all();

        // Find root labels (no incoming edges)
        $childIds = $relationships->pluck('child_label_id')->unique();
        $roots = $labels->reject(fn (Label $l): bool => $childIds->contains($l->id));

        /** @var array<int, string> $output */
        $output = [];

        foreach ($roots as $root) {
            $this->renderTreeNode($root, $labels, $relationships, $output, 0, $includeRoutes);
        }

        // Show orphan labels (no relationships at all)
        $connectedIds = $relationships->pluck('parent_label_id')
            ->merge($relationships->pluck('child_label_id'))
            ->unique();
        $orphans = $labels->reject(fn (Label $l): bool => $connectedIds->contains($l->id));

        if ($orphans->isNotEmpty()) {
            $output[] = '';
            $output[] = '(Unconnected labels)';
            foreach ($orphans as $orphan) {
                $output[] = "  {$orphan->slug}";
            }
        }

        return implode("\n", $output);
    }

    /**
     * @param  Collection<string, Label>  $labels
     * @param  Collection<int, LabelRelationship>  $relationships
     * @param  array<int, string>  $output
     */
    protected function renderTreeNode(
        Label $label,
        Collection $labels,
        Collection $relationships,
        array &$output,
        int $depth,
        bool $includeRoutes
    ): void {
        $indent = str_repeat('  ', $depth);
        $children = $relationships->where('parent_label_id', $label->id);

        $routeCount = '';
        if ($includeRoutes) {
            $count = LabelRoute::where('path', 'LIKE', "%{$label->slug}%")->count();
            $routeCount = " ({$count} routes)";
        }

        $output[] = "{$indent}{$label->slug}{$routeCount}";

        foreach ($children as $rel) {
            $child = $labels[$rel->child_label_id] ?? null;
            if ($child !== null) {
                $this->renderTreeNode($child, $labels, $relationships, $output, $depth + 1, $includeRoutes);
            }
        }
    }

    /**
     * Render the label graph as ASCII tree with box-drawing characters.
     */
    public function renderAscii(bool $includeRoutes = false): string
    {
        /** @var Collection<string, Label> $labels */
        $labels = Label::all()->keyBy('id');
        $relationships = LabelRelationship::all();

        $childIds = $relationships->pluck('child_label_id')->unique();
        $roots = $labels->reject(fn (Label $l): bool => $childIds->contains($l->id));

        /** @var array<int, string> $output */
        $output = [];

        $rootsArray = $roots->values()->all();
        $count = count($rootsArray);

        foreach ($rootsArray as $i => $root) {
            $isLast = $i === $count - 1;
            $this->renderAsciiNode($root, $labels, $relationships, $output, '', $isLast, $includeRoutes);
        }

        return implode("\n", $output);
    }

    /**
     * @param  Collection<string, Label>  $labels
     * @param  Collection<int, LabelRelationship>  $relationships
     * @param  array<int, string>  $output
     */
    protected function renderAsciiNode(
        Label $label,
        Collection $labels,
        Collection $relationships,
        array &$output,
        string $prefix,
        bool $isLast,
        bool $includeRoutes
    ): void {
        $connector = $isLast ? '└── ' : '├── ';

        $routeInfo = '';
        if ($includeRoutes) {
            $count = LabelRoute::where('path', 'LIKE', "%{$label->slug}%")->count();
            $routeInfo = " [{$count}]";
        }

        $output[] = $prefix.$connector.$label->slug.$routeInfo;

        $children = $relationships->where('parent_label_id', $label->id);
        $childPrefix = $prefix.($isLast ? '    ' : '│   ');

        /** @var Collection<int, Label> $childLabels */
        $childLabels = $children->map(fn (LabelRelationship $r): ?Label => $labels[$r->child_label_id] ?? null)->filter();

        $childArray = $childLabels->values()->all();
        $childCount = count($childArray);

        foreach ($childArray as $i => $child) {
            $childIsLast = $i === $childCount - 1;
            $this->renderAsciiNode($child, $labels, $relationships, $output, $childPrefix, $childIsLast, $includeRoutes);
        }
    }

    /**
     * Render the label graph as JSON.
     */
    public function renderJson(bool $includeRoutes = false): string
    {
        /** @var array<string, mixed> $data */
        $data = [
            'labels' => Label::all()->map(fn (Label $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'slug' => $l->slug,
                'color' => $l->color,
                'icon' => $l->icon,
            ])->values()->all(),
            'relationships' => LabelRelationship::with(['parent', 'child'])->get()
                ->filter(fn (LabelRelationship $r): bool => $r->parent !== null && $r->child !== null)
                ->map(function (LabelRelationship $r): array {
                    /** @var Label $parent */
                    $parent = $r->parent;
                    /** @var Label $child */
                    $child = $r->child;

                    return [
                        'parent' => $parent->slug,
                        'child' => $child->slug,
                    ];
                })->values()->all(),
        ];

        if ($includeRoutes) {
            $data['routes'] = LabelRoute::orderBy('path')->pluck('path')->values()->all();
        }

        $json = json_encode($data, JSON_PRETTY_PRINT);

        return $json !== false ? $json : '{}';
    }
}
