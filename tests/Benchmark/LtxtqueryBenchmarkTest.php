<?php

declare(strict_types=1);

use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;
use Birdcar\LabelTree\Tests\Benchmark\BenchmarkResultCollector;

beforeEach(function (): void {
    // Create a representative hierarchy for benchmarking
    $status = Label::create(['name' => 'Status']);
    $open = Label::create(['name' => 'Open']);
    $closed = Label::create(['name' => 'Closed']);

    LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $open->id]);
    LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $closed->id]);

    $priority = Label::create(['name' => 'Priority']);
    $high = Label::create(['name' => 'High']);
    $low = Label::create(['name' => 'Low']);

    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $high->id]);
    LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $low->id]);
});

describe('Ltxtquery Benchmarks', function (): void {
    it('benchmarks simple word match', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('ltxtquery_word', function (): void {
            LabelRoute::wherePathMatchesText('status')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(50);
    })->group('benchmark');

    it('benchmarks boolean AND', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('ltxtquery_and', function (): void {
            LabelRoute::wherePathMatchesText('status & open')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark');

    it('benchmarks boolean OR', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('ltxtquery_or', function (): void {
            LabelRoute::wherePathMatchesText('status | priority')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(100);
    })->group('benchmark');

    it('benchmarks complex boolean', function (): void {
        $collector = BenchmarkResultCollector::instance();

        $result = $collector->measure('ltxtquery_complex', function (): void {
            LabelRoute::wherePathMatchesText('(status | priority) & !closed')->get();
        });

        expect($result['avg_ms'])->toBeLessThan(150);
    })->group('benchmark');
})->group('benchmark');
