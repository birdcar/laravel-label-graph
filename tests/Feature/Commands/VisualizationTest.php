<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;

it('visualizes empty graph', function (): void {
    $this->artisan('label-graph:visualize')
        ->assertSuccessful()
        ->expectsOutput('No labels found.');
});

it('visualizes label tree in tree format', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    // Run the command and verify it succeeds
    $this->artisan('label-graph:visualize', ['--format' => 'tree'])
        ->assertSuccessful()
        ->expectsOutputToContain('parent');

    // Verify the visualizer produces correct output
    $visualizer = app(\Birdcar\LabelGraph\Services\GraphVisualizer::class);
    $output = $visualizer->renderTree(false);
    expect($output)->toContain('parent');
    expect($output)->toContain('child');
});

it('visualizes label tree in ascii format', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-graph:visualize', ['--format' => 'ascii'])
        ->assertSuccessful()
        ->expectsOutputToContain('parent');
});

it('visualizes label tree in json format', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    // Run the command and verify it succeeds
    $this->artisan('label-graph:visualize', ['--format' => 'json'])
        ->assertSuccessful();

    // Verify the visualizer produces correct JSON output
    $visualizer = app(\Birdcar\LabelGraph\Services\GraphVisualizer::class);
    $output = $visualizer->renderJson(false);
    expect($output)->toContain('"labels"');
    expect($output)->toContain('"relationships"');

    $data = json_decode($output, true);
    expect($data)->toHaveKey('labels');
    expect($data)->toHaveKey('relationships');
});

it('includes routes in visualization', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-graph:visualize', ['--format' => 'json', '--routes' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"routes"');
});

it('fails on unknown format', function (): void {
    Label::create(['name' => 'Test']);

    $this->artisan('label-graph:visualize', ['--format' => 'invalid'])
        ->assertFailed()
        ->expectsOutput('Unknown format: invalid');
});

it('shows orphan labels separately', function (): void {
    Label::create(['name' => 'Connected']);
    Label::create(['name' => 'Orphan']);

    // Only one label, no relationships = all are orphans
    $this->artisan('label-graph:visualize', ['--format' => 'tree'])
        ->assertSuccessful()
        ->expectsOutputToContain('Unconnected labels');
});
