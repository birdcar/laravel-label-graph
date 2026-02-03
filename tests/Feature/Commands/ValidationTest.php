<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;

it('validates clean graph with no issues', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-graph:validate')
        ->assertSuccessful()
        ->expectsOutput('Validating label graph...')
        ->expectsOutput('No issues found.');
});

it('validates empty graph', function (): void {
    $this->artisan('label-graph:validate')
        ->assertSuccessful()
        ->expectsOutput('No issues found.');
});

it('detects orphaned routes', function (): void {
    // Create a valid route first
    $label = Label::create(['name' => 'Valid']);

    // Now manually create an orphaned route
    LabelRoute::create(['path' => 'orphan.path', 'depth' => 1]);

    $this->artisan('label-graph:validate')
        ->assertFailed()
        ->expectsOutputToContain('Orphaned route');
});

it('detects depth mismatches', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    // Manually corrupt a route depth
    LabelRoute::where('path', 'parent.child')->update(['depth' => 99]);

    $this->artisan('label-graph:validate')
        ->assertFailed()
        ->expectsOutputToContain('depth');
});

it('auto-fixes depth mismatches', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    // Manually corrupt a route depth
    LabelRoute::where('path', 'parent.child')->update(['depth' => 99]);

    $this->artisan('label-graph:validate', ['--fix' => true])
        ->assertFailed()
        ->expectsOutputToContain('Fixed');

    // Verify the depth was corrected
    expect(LabelRoute::where('path', 'parent.child')->first()->depth)->toBe(1);
});

it('detects routes referencing missing labels', function (): void {
    // Create a route that references a non-existent label
    LabelRoute::create(['path' => 'missing.label', 'depth' => 1]);

    $this->artisan('label-graph:validate')
        ->assertFailed()
        ->expectsOutputToContain('non-existent label');
});
