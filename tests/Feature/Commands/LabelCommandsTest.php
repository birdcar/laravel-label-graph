<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;

it('creates a label with auto-generated slug', function (): void {
    $this->artisan('label-graph:label:create', ['name' => 'Bug Report'])
        ->assertSuccessful()
        ->expectsOutput('Label created: Bug Report (bug-report)');

    expect(Label::where('slug', 'bug-report')->exists())->toBeTrue();
});

it('creates a label with custom slug', function (): void {
    $this->artisan('label-graph:label:create', [
        'name' => 'Bug Report',
        '--slug' => 'custom-bug',
    ])
        ->assertSuccessful()
        ->expectsOutput('Label created: Bug Report (custom-bug)');

    expect(Label::where('slug', 'custom-bug')->exists())->toBeTrue();
});

it('creates a label with all options', function (): void {
    $this->artisan('label-graph:label:create', [
        'name' => 'Priority',
        '--slug' => 'priority',
        '--color' => '#FF0000',
        '--icon' => 'flag',
        '--description' => 'Priority level indicator',
    ])
        ->assertSuccessful();

    $label = Label::where('slug', 'priority')->first();
    expect($label->color)->toBe('#FF0000');
    expect($label->icon)->toBe('flag');
    expect($label->description)->toBe('Priority level indicator');
});

it('lists all labels', function (): void {
    Label::create(['name' => 'Alpha']);
    Label::create(['name' => 'Beta']);

    $this->artisan('label-graph:label:list')
        ->assertSuccessful()
        ->expectsTable(
            ['ID', 'Name', 'Slug', 'Color', 'Icon', 'Relationships'],
            Label::orderBy('name')->get()->map(fn (Label $l): array => [
                $l->id,
                $l->name,
                $l->slug,
                $l->color ?? '-',
                $l->icon ?? '-',
                $l->relationships()->count().' children',
            ])->toArray()
        );
});

it('shows message when no labels exist', function (): void {
    $this->artisan('label-graph:label:list')
        ->assertSuccessful()
        ->expectsOutput('No labels found.');
});

it('updates a label name', function (): void {
    Label::create(['name' => 'Old Name', 'slug' => 'test']);

    $this->artisan('label-graph:label:update', [
        'slug' => 'test',
        '--name' => 'New Name',
    ])
        ->assertSuccessful();

    expect(Label::where('slug', 'test')->first()->name)->toBe('New Name');
});

it('updates a label slug', function (): void {
    Label::create(['name' => 'Test', 'slug' => 'old-slug']);

    $this->artisan('label-graph:label:update', [
        'slug' => 'old-slug',
        '--new-slug' => 'new-slug',
    ])
        ->assertSuccessful();

    expect(Label::where('slug', 'new-slug')->exists())->toBeTrue();
    expect(Label::where('slug', 'old-slug')->exists())->toBeFalse();
});

it('fails to update non-existent label', function (): void {
    $this->artisan('label-graph:label:update', [
        'slug' => 'non-existent',
        '--name' => 'New Name',
    ])
        ->assertFailed()
        ->expectsOutput('Label not found: non-existent');
});

it('warns when no updates provided', function (): void {
    Label::create(['name' => 'Test', 'slug' => 'test']);

    $this->artisan('label-graph:label:update', ['slug' => 'test'])
        ->assertSuccessful()
        ->expectsOutput('No updates provided.');
});

it('deletes a label with force flag', function (): void {
    Label::create(['name' => 'ToDelete', 'slug' => 'to-delete']);

    $this->artisan('label-graph:label:delete', [
        'slug' => 'to-delete',
        '--force' => true,
    ])
        ->assertSuccessful()
        ->expectsOutput('Label deleted: ToDelete');

    expect(Label::where('slug', 'to-delete')->exists())->toBeFalse();
});

it('fails to delete non-existent label', function (): void {
    $this->artisan('label-graph:label:delete', ['slug' => 'non-existent'])
        ->assertFailed()
        ->expectsOutput('Label not found: non-existent');
});
