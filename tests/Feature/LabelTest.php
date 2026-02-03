<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;

it('creates a label with all attributes', function (): void {
    $label = Label::create([
        'name' => 'Technology',
        'slug' => 'tech',
        'color' => '#3498db',
        'icon' => 'computer',
        'description' => 'Technology related topics',
    ]);

    expect($label)->toBeInstanceOf(Label::class);
    expect($label->name)->toBe('Technology');
    expect($label->slug)->toBe('tech');
    expect($label->color)->toBe('#3498db');
    expect($label->icon)->toBe('computer');
    expect($label->description)->toBe('Technology related topics');
});

it('auto-generates slug from name if not provided', function (): void {
    $label = Label::create(['name' => 'Web Development']);

    expect($label->slug)->toBe('web-development');
});

it('uses provided slug instead of auto-generating', function (): void {
    $label = Label::create([
        'name' => 'Web Development',
        'slug' => 'webdev',
    ]);

    expect($label->slug)->toBe('webdev');
});

it('generates a ULID for the primary key', function (): void {
    $label = Label::create(['name' => 'Test']);

    expect($label->id)->toBeString();
    expect(strlen($label->id))->toBe(26);
});

it('allows nullable fields', function (): void {
    $label = Label::create(['name' => 'Minimal']);

    expect($label->color)->toBeNull();
    expect($label->icon)->toBeNull();
    expect($label->description)->toBeNull();
});

it('can be updated', function (): void {
    $label = Label::create(['name' => 'Original']);

    $label->update([
        'name' => 'Updated',
        'color' => '#ff0000',
    ]);

    $label->refresh();

    expect($label->name)->toBe('Updated');
    expect($label->color)->toBe('#ff0000');
});

it('can be deleted', function (): void {
    $label = Label::create(['name' => 'ToDelete']);
    $id = $label->id;

    $label->delete();

    expect(Label::find($id))->toBeNull();
});

it('enforces unique slugs', function (): void {
    Label::create(['name' => 'First', 'slug' => 'unique-slug']);

    Label::create(['name' => 'Second', 'slug' => 'unique-slug']);
})->throws(\Illuminate\Database\QueryException::class)
    ->skip(fn () => usingPostgres(), 'PostgreSQL aborts transactions on constraint violations');
