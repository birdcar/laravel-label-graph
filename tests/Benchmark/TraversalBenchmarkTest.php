<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Tests\Benchmark\BenchmarkResultCollector;

/**
 * Create a deep hierarchy directly as routes (bypasses Label/Relationship for benchmark purity).
 * This avoids MySQL savepoint nesting limits when creating 50+ level hierarchies.
 * Uses short segment names (l1, l2, etc.) to stay within column size limits.
 */
function createDeepRouteHierarchy(int $depth, string $prefix = 'd'): void
{
    $path = $prefix;
    LabelRoute::create(['path' => $path, 'depth' => 0]);

    for ($i = 1; $i <= $depth; $i++) {
        $path .= ".l{$i}";
        LabelRoute::create(['path' => $path, 'depth' => $i]);
    }
}

/**
 * Create a wide hierarchy directly as routes (bypasses Label/Relationship for benchmark purity).
 */
function createWideRouteHierarchy(int $width, string $prefix = 'wide'): void
{
    LabelRoute::create(['path' => $prefix, 'depth' => 0]);

    for ($i = 0; $i < $width; $i++) {
        LabelRoute::create(['path' => "{$prefix}.child{$i}", 'depth' => 1]);
    }
}

describe('ancestor query benchmarks', function (): void {
    it('benchmarks ancestors at depth 50', function (): void {
        createDeepRouteHierarchy(50, 'a50');

        $deepest = LabelRoute::where('path', 'like', 'a50.%')->orderBy('depth', 'desc')->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('ancestors_depth_50', function () use ($deepest): void {
            $deepest->ancestors();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark', 'traversal');

    it('benchmarks ancestorsAndSelf at depth 100', function (): void {
        createDeepRouteHierarchy(100, 'a100');

        $deepest = LabelRoute::where('path', 'like', 'a100.%')->orderBy('depth', 'desc')->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('ancestorsAndSelf_depth_100', function () use ($deepest): void {
            $deepest->ancestorsAndSelf();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark', 'traversal');
});

describe('descendant query benchmarks', function (): void {
    it('benchmarks descendants with width 100', function (): void {
        createWideRouteHierarchy(100, 'desc100');

        $rootRoute = LabelRoute::where('path', 'desc100')->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('descendants_width_100', function () use ($rootRoute): void {
            $rootRoute->descendants();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark', 'traversal');

    it('benchmarks descendants with width 500', function (): void {
        createWideRouteHierarchy(500, 'desc500');

        $rootRoute = LabelRoute::where('path', 'desc500')->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('descendants_width_500', function () use ($rootRoute): void {
            $rootRoute->descendants();
        }, 25);

        expect($result['avg_ms'])->toBeLessThan(150);
    })->group('benchmark', 'traversal');
});

describe('toTree benchmarks', function (): void {
    it('benchmarks tree building with 100 routes', function (): void {
        LabelRoute::create(['path' => 'tree_root_100', 'depth' => 0]);

        for ($i = 0; $i < 100; $i++) {
            LabelRoute::create(['path' => "tree_root_100.child_{$i}", 'depth' => 1]);
        }

        $routes = LabelRoute::where('path', 'like', 'tree_root_100%')->get();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('toTree_100', function () use ($routes): void {
            $routes->toTree();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark', 'traversal');

    it('benchmarks tree building with 500 routes', function (): void {
        LabelRoute::create(['path' => 'tree_root_500', 'depth' => 0]);

        for ($i = 0; $i < 500; $i++) {
            LabelRoute::create(['path' => "tree_root_500.child_{$i}", 'depth' => 1]);
        }

        $routes = LabelRoute::where('path', 'like', 'tree_root_500%')->get();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('toTree_500', function () use ($routes): void {
            $routes->toTree();
        }, 25);

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark', 'traversal');
});

describe('sibling benchmarks', function (): void {
    it('benchmarks siblings with many siblings', function (): void {
        createWideRouteHierarchy(200, 'sib200');

        $child = LabelRoute::where('path', 'like', 'sib200.child%')->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('siblings_200', function () use ($child): void {
            $child->siblings();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark', 'traversal');
});

describe('bloodline benchmarks', function (): void {
    it('benchmarks bloodline at depth 50', function (): void {
        createDeepRouteHierarchy(50, 'b50');

        $mid = LabelRoute::where('path', 'like', 'b50.%')->where('depth', 25)->first();

        $collector = BenchmarkResultCollector::instance();
        $result = $collector->measure('bloodline_depth_50', function () use ($mid): void {
            $mid->bloodline();
        }, 25);

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark', 'traversal');
});
