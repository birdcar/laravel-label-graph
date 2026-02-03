<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;

it('lists all routes', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-graph:route:list')
        ->assertSuccessful();

    // Routes are auto-generated via observer
    expect(LabelRoute::count())->toBeGreaterThan(0);
});

it('shows message when no routes exist', function (): void {
    $this->artisan('label-graph:route:list')
        ->assertSuccessful()
        ->expectsOutput('No routes found.');
});

it('filters routes by path pattern', function (): void {
    $a = Label::create(['name' => 'Alpha']);
    $b = Label::create(['name' => 'Beta']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    $this->artisan('label-graph:route:list', ['--filter' => 'alpha'])
        ->assertSuccessful();
});

it('filters routes by depth', function (): void {
    $a = Label::create(['name' => 'Alpha']);
    $b = Label::create(['name' => 'Beta']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    $this->artisan('label-graph:route:list', ['--depth' => 1])
        ->assertSuccessful();
});

it('regenerates all routes', function (): void {
    $a = Label::create(['name' => 'Alpha']);
    $b = Label::create(['name' => 'Beta']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    $initialCount = LabelRoute::count();

    $this->artisan('label-graph:route:regenerate', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Routes regenerated');
});

it('prunes orphaned routes when none exist', function (): void {
    $a = Label::create(['name' => 'Alpha']);

    // Just one label, one route
    $this->artisan('label-graph:route:prune')
        ->assertSuccessful()
        ->expectsOutput('No orphaned routes found.');
});

it('prunes orphaned routes with force', function (): void {
    $a = Label::create(['name' => 'Alpha']);
    $b = Label::create(['name' => 'Beta']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    // Manually create an orphaned route
    LabelRoute::create(['path' => 'orphan.route', 'depth' => 1]);

    $this->artisan('label-graph:route:prune', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Deleted');

    expect(LabelRoute::where('path', 'orphan.route')->exists())->toBeFalse();
});
