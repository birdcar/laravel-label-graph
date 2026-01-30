<?php

declare(strict_types=1);

use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;

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
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::wherePathMatches('status.*')->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(50); // Average should be under 50ms per query
    })->group('benchmark');

    it('queries with exact path efficiently', function (): void {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::wherePathMatches('status.open')->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(20); // Exact matches should be faster
    })->group('benchmark');

    it('finds descendants efficiently', function (): void {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::whereDescendantOf('area')->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(30);
    })->group('benchmark');

    it('finds ancestors efficiently', function (): void {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::whereAncestorOf('area.frontend.components.button')->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(20);
    })->group('benchmark');

    it('filters by depth efficiently', function (): void {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::whereDepth(1)->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(15);
    })->group('benchmark');

    it('handles quantified patterns efficiently', function (): void {
        $startTime = microtime(true);
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            LabelRoute::wherePathMatches('*{2}')->get();
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $avgMs = $elapsed / $iterations;

        expect($avgMs)->toBeLessThan(30);
    })->group('benchmark');
})->group('benchmark');
