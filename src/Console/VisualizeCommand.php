<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Services\GraphVisualizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class VisualizeCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:visualize
        {--format=tree : Output format (tree, ascii, json, dot, mermaid)}
        {--routes : Include generated routes}
        {--highlight= : Pattern to highlight (for dot/mermaid formats)}';

    /** @var string */
    protected $description = 'Visualize the label graph';

    public function handle(GraphVisualizer $visualizer): int
    {
        /** @var string $format */
        $format = $this->option('format');
        $includeRoutes = (bool) $this->option('routes');
        /** @var string|null $highlight */
        $highlight = $this->option('highlight');

        try {
            $output = match ($format) {
                'tree' => $visualizer->renderTree($includeRoutes),
                'ascii' => $visualizer->renderAscii($includeRoutes),
                'json' => $visualizer->renderJson($includeRoutes),
                'dot' => $this->outputDot($highlight),
                'mermaid' => $this->outputMermaid($highlight),
                default => throw new InvalidArgumentException("Unknown format: {$format}"),
            };
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($output === '') {
            $this->info('No labels found.');

            return Command::SUCCESS;
        }

        $this->line($output);

        return Command::SUCCESS;
    }

    /**
     * Output in GraphViz DOT format.
     */
    protected function outputDot(?string $highlight): string
    {
        /** @var Collection<int, LabelRoute> $routes */
        $routes = LabelRoute::orderBy('path')->get();

        if ($routes->isEmpty()) {
            return '';
        }

        $lines = ['digraph G {'];
        $lines[] = '  rankdir=TB;';
        $lines[] = '  node [shape=box];';

        foreach ($routes as $route) {
            $parent = $route->parent();
            if ($parent) {
                $highlighted = $highlight && $this->matchesPattern($route->path, $highlight);
                $style = $highlighted ? ' [color=red, penwidth=2]' : '';
                $lines[] = "  \"{$parent->path}\" -> \"{$route->path}\"{$style};";
            } else {
                // Root node
                $highlighted = $highlight && $this->matchesPattern($route->path, $highlight);
                $style = $highlighted ? ' [color=red, penwidth=2]' : '';
                $lines[] = "  \"{$route->path}\"{$style};";
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Output in Mermaid format.
     */
    protected function outputMermaid(?string $highlight): string
    {
        /** @var Collection<int, LabelRoute> $routes */
        $routes = LabelRoute::orderBy('path')->get();

        if ($routes->isEmpty()) {
            return '';
        }

        $lines = ['graph TD'];

        foreach ($routes as $route) {
            $parent = $route->parent();
            $childId = str_replace('.', '_', $route->path);

            if ($parent) {
                $parentId = str_replace('.', '_', $parent->path);
                $lines[] = "  {$parentId}[{$parent->path}] --> {$childId}[{$route->path}]";
            } else {
                // Root node
                $lines[] = "  {$childId}[{$route->path}]";
            }
        }

        // Add highlight styles
        if ($highlight) {
            $highlightedIds = [];
            foreach ($routes as $route) {
                if ($this->matchesPattern($route->path, $highlight)) {
                    $highlightedIds[] = str_replace('.', '_', $route->path);
                }
            }
            if (! empty($highlightedIds)) {
                $lines[] = '  style '.implode(',', $highlightedIds).' fill:#f96';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Check if a path matches a simple pattern (supports * wildcard).
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert simple glob-like pattern to regex
        $regex = '/^'.str_replace(['.', '*'], ['\.', '.*'], $pattern).'$/';

        return preg_match($regex, $path) === 1;
    }
}
