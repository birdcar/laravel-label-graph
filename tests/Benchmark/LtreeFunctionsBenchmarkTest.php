<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Ltree\Ltree;
use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Tests\Benchmark\BenchmarkResultCollector;

beforeEach(function (): void {
    // Create a hierarchy for query benchmarks
    $area = Label::create(['name' => 'Area']);
    $frontend = Label::create(['name' => 'Frontend']);
    $components = Label::create(['name' => 'Components']);
    $button = Label::create(['name' => 'Button']);

    LabelRelationship::create(['parent_label_id' => $area->id, 'child_label_id' => $frontend->id]);
    LabelRelationship::create(['parent_label_id' => $frontend->id, 'child_label_id' => $components->id]);
    LabelRelationship::create(['parent_label_id' => $components->id, 'child_label_id' => $button->id]);
});

describe('Ltree Functions Benchmarks', function (): void {
    it('benchmarks nlevel in PHP', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('nlevel_php', function (): void {
            Ltree::nlevel('area.frontend.components.button');
        }, 1000);

        expect($result['avg_ms'])->toBeLessThan(0.1);
    })->group('benchmark');

    it('benchmarks nlevel in query', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('nlevel_query', function (): void {
            LabelRoute::selectNlevel()->get();
        });

        expect($result['avg_ms'])->toBeLessThan(30);
    })->group('benchmark');

    it('benchmarks subpath in PHP', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('subpath_php', function (): void {
            Ltree::subpath('area.frontend.components.button', 1, 2);
        }, 1000);

        expect($result['avg_ms'])->toBeLessThan(0.1);
    })->group('benchmark');

    it('benchmarks subpath in query', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('subpath_query', function (): void {
            LabelRoute::selectSubpath(0, 2)->get();
        });

        expect($result['avg_ms'])->toBeLessThan(30);
    })->group('benchmark');

    it('benchmarks lca calculation', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $paths = ['a.b.c.d', 'a.b.c.e', 'a.b.f', 'a.g'];

        $result = $collector->measure('lca_php', function () use ($paths): void {
            Ltree::lca($paths);
        }, 1000);

        expect($result['avg_ms'])->toBeLessThan(0.5);
    })->group('benchmark');

    it('benchmarks index in PHP', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('index_php', function (): void {
            Ltree::index('area.frontend.components.button', 'components');
        }, 1000);

        expect($result['avg_ms'])->toBeLessThan(0.1);
    })->group('benchmark');

    it('benchmarks concat in PHP', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('concat_php', function (): void {
            Ltree::concat('area.frontend', 'components.button');
        }, 1000);

        expect($result['avg_ms'])->toBeLessThan(0.1);
    })->group('benchmark');
})->group('benchmark');
