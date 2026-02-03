<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Tests\Benchmark\BenchmarkResultCollector;

/**
 * Create a larger dataset for scaling tests.
 * Creates a hierarchy with multiple roots and varying depths.
 */
function createLargeDataset(int $targetRoutes): void
{
    $rootCount = (int) ceil($targetRoutes / 10);
    $childrenPerRoot = 3;
    $grandchildrenPerChild = 2;

    for ($r = 0; $r < $rootCount; $r++) {
        $root = Label::create(['name' => "root-{$r}"]);

        for ($c = 0; $c < $childrenPerRoot; $c++) {
            $child = Label::create(['name' => "root-{$r}-child-{$c}"]);
            LabelRelationship::create(['parent_label_id' => $root->id, 'child_label_id' => $child->id]);

            for ($g = 0; $g < $grandchildrenPerChild; $g++) {
                $grandchild = Label::create(['name' => "root-{$r}-child-{$c}-grandchild-{$g}"]);
                LabelRelationship::create(['parent_label_id' => $child->id, 'child_label_id' => $grandchild->id]);
            }
        }

        // Stop if we've created enough routes
        if (LabelRoute::count() >= $targetRoutes) {
            break;
        }
    }
}

describe('Scaling Benchmarks', function (): void {
    it('benchmarks with 100 routes', function (): void {
        createLargeDataset(100);

        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('star_pattern_100', function (): void {
            LabelRoute::wherePathMatches('*')->get();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark', 'scaling');

    it('benchmarks descendant query with 100 routes', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('descendants_100', function (): void {
            LabelRoute::whereDescendantOf('root-0')->get();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark', 'scaling');

    it('benchmarks depth filter with 100 routes', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('depth_filter_100', function (): void {
            LabelRoute::whereDepth(1)->get();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(30);
    })->group('benchmark', 'scaling');

    it('benchmarks pattern matching with 100 routes', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('pattern_match_100', function (): void {
            LabelRoute::wherePathMatches('root-*.*')->get();
        }, 50);

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark', 'scaling');
})->group('benchmark', 'scaling');
