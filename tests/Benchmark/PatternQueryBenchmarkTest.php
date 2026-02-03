<?php

declare(strict_types=1);

use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;
use Birdcar\LabelTree\Tests\Benchmark\BenchmarkResultCollector;

beforeAll(function (): void {
    BenchmarkResultCollector::instance()
        ->reset()
        ->setOutputPath(__DIR__.'/../../benchmark-results.json');
});

beforeEach(function (): void {
    // Create a representative hierarchy for benchmarking
    // Status (root) -> Open, In Progress, Closed
    $status = Label::create(['name' => 'Status']);
    $open = Label::create(['name' => 'Open']);
    $inProgress = Label::create(['name' => 'In Progress']);
    $closed = Label::create(['name' => 'Closed']);

    LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $open->id]);
    LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $inProgress->id]);
    LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $closed->id]);

    // Priority (root) -> Low, Medium, High, Critical
    $priority = Label::create(['name' => 'Priority']);
    $low = Label::create(['name' => 'Low']);
    $medium = Label::create(['name' => 'Medium']);
    $high = Label::create(['name' => 'High']);
    $critical = Label::create(['name' => 'Critical']);

    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $low->id]);
    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $medium->id]);
    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $high->id]);
    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $critical->id]);

    // Type (root) -> Bug, Feature, Documentation
    $type = Label::create(['name' => 'Type']);
    $bug = Label::create(['name' => 'Bug']);
    $feature = Label::create(['name' => 'Feature']);
    $docs = Label::create(['name' => 'Documentation']);

    LabelRelationship::create(['parent_label_id' => $type->id, 'child_label_id' => $bug->id]);
    LabelRelationship::create(['parent_label_id' => $type->id, 'child_label_id' => $feature->id]);
    LabelRelationship::create(['parent_label_id' => $type->id, 'child_label_id' => $docs->id]);

    // Nested hierarchy: Area -> Frontend -> Components -> Button
    $area = Label::create(['name' => 'Area']);
    $frontend = Label::create(['name' => 'Frontend']);
    $components = Label::create(['name' => 'Components']);
    $button = Label::create(['name' => 'Button']);

    LabelRelationship::create(['parent_label_id' => $area->id, 'child_label_id' => $frontend->id]);
    LabelRelationship::create(['parent_label_id' => $frontend->id, 'child_label_id' => $components->id]);
    LabelRelationship::create(['parent_label_id' => $components->id, 'child_label_id' => $button->id]);
});

describe('Pattern Query Benchmarks', function (): void {
    it('queries with star pattern efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('star_pattern', function (): void {
            LabelRoute::wherePathMatches('status.*')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark');

    it('queries with exact path efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('exact_path', function (): void {
            LabelRoute::wherePathMatches('status.open')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(20);
    })->group('benchmark');

    it('finds descendants efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('descendants', function (): void {
            LabelRoute::whereDescendantOf('area')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(30);
    })->group('benchmark');

    it('finds ancestors efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('ancestors', function (): void {
            LabelRoute::whereAncestorOf('area.frontend.components.button')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(20);
    })->group('benchmark');

    it('filters by depth efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('depth_filter', function (): void {
            LabelRoute::whereDepth(1)->get();
        });

        expect($result['avg_ms'])->toBeLessThan(15);
    })->group('benchmark');

    it('handles quantified patterns efficiently', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('quantified_pattern', function (): void {
            LabelRoute::wherePathMatches('*{2}')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(30);
    })->group('benchmark');
})->group('benchmark');
