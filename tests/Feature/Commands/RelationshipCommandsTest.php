<?php

declare(strict_types=1);

use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;

it('creates a relationship between labels', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'parent',
        'child' => 'child',
    ])
        ->assertSuccessful()
        ->expectsOutput('Relationship created: parent -> child');

    expect(LabelRelationship::where([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ])->exists())->toBeTrue();
});

it('fails when parent label does not exist', function (): void {
    Label::create(['name' => 'Child']);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'non-existent',
        'child' => 'child',
    ])
        ->assertFailed()
        ->expectsOutput('Parent label not found: non-existent');
});

it('fails when child label does not exist', function (): void {
    Label::create(['name' => 'Parent']);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'parent',
        'child' => 'non-existent',
    ])
        ->assertFailed()
        ->expectsOutput('Child label not found: non-existent');
});

it('fails on self-referential relationship', function (): void {
    Label::create(['name' => 'Self']);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'self',
        'child' => 'self',
    ])
        ->assertFailed()
        ->expectsOutput('Cannot create self-referential relationship.');
});

it('fails on cycle creation', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'b',
        'child' => 'a',
    ])
        ->assertFailed()
        ->expectsOutput('Cannot create relationship: would form a cycle.');
});

it('fails on duplicate relationship', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-tree:relationship:create', [
        'parent' => 'parent',
        'child' => 'child',
    ])
        ->assertFailed()
        ->expectsOutput('Relationship already exists.');
})->skip(fn () => usingPostgres(), 'PostgreSQL aborts transactions on constraint violations');

it('lists all relationships', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-tree:relationship:list')
        ->assertSuccessful();

    expect(LabelRelationship::count())->toBe(1);
});

it('shows message when no relationships exist', function (): void {
    $this->artisan('label-tree:relationship:list')
        ->assertSuccessful()
        ->expectsOutput('No relationships found.');
});

it('deletes a relationship with no attachments', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    $this->artisan('label-tree:relationship:delete', [
        'parent' => 'parent',
        'child' => 'child',
    ])
        ->assertSuccessful()
        ->expectsOutput('Relationship deleted.');

    expect(LabelRelationship::count())->toBe(0);
});

it('fails to delete non-existent relationship', function (): void {
    Label::create(['name' => 'Parent']);
    Label::create(['name' => 'Child']);

    $this->artisan('label-tree:relationship:delete', [
        'parent' => 'parent',
        'child' => 'child',
    ])
        ->assertFailed()
        ->expectsOutput('Relationship not found.');
});

it('fails to delete when labels not found', function (): void {
    $this->artisan('label-tree:relationship:delete', [
        'parent' => 'non-existent',
        'child' => 'also-non-existent',
    ])
        ->assertFailed()
        ->expectsOutput('Parent or child label not found.');
});
