<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;

it('generates routes when a relationship is created', function (): void {
    $parent = Label::create(['name' => 'Tech']);
    $child = Label::create(['name' => 'Backend']);

    // Before relationship
    expect(LabelRoute::where('path', 'tech.backend')->exists())->toBeFalse();

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    // After relationship
    expect(LabelRoute::where('path', 'tech.backend')->exists())->toBeTrue();
});

it('prunes routes when a relationship is deleted', function (): void {
    $parent = Label::create(['name' => 'Tech']);
    $child = Label::create(['name' => 'Backend']);

    $relationship = LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    expect(LabelRoute::where('path', 'tech.backend')->exists())->toBeTrue();

    $relationship->delete();

    expect(LabelRoute::where('path', 'tech.backend')->exists())->toBeFalse();
});

it('generates correct routes for a complex hierarchy', function (): void {
    // Create a hierarchy: Tech -> (Backend, Frontend)
    // Backend -> (PHP, Python)
    $tech = Label::create(['name' => 'Tech']);
    $backend = Label::create(['name' => 'Backend']);
    $frontend = Label::create(['name' => 'Frontend']);
    $php = Label::create(['name' => 'PHP']);
    $python = Label::create(['name' => 'Python']);

    LabelRelationship::create([
        'parent_label_id' => $tech->id,
        'child_label_id' => $backend->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $tech->id,
        'child_label_id' => $frontend->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $backend->id,
        'child_label_id' => $php->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $backend->id,
        'child_label_id' => $python->id,
    ]);

    $routes = LabelRoute::pluck('path')->toArray();

    // Single labels
    expect($routes)->toContain('tech');
    expect($routes)->toContain('backend');
    expect($routes)->toContain('frontend');
    expect($routes)->toContain('php');
    expect($routes)->toContain('python');

    // Two-level routes
    expect($routes)->toContain('tech.backend');
    expect($routes)->toContain('tech.frontend');
    expect($routes)->toContain('backend.php');
    expect($routes)->toContain('backend.python');

    // Three-level routes
    expect($routes)->toContain('tech.backend.php');
    expect($routes)->toContain('tech.backend.python');
});

it('sets correct depth on routes', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);

    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $c->id,
    ]);

    expect(LabelRoute::where('path', 'a')->first()->depth)->toBe(0);
    expect(LabelRoute::where('path', 'a.b')->first()->depth)->toBe(1);
    expect(LabelRoute::where('path', 'a.b.c')->first()->depth)->toBe(2);
});

it('retrieves labels from a route in order', function (): void {
    $tech = Label::create(['name' => 'Tech']);
    $backend = Label::create(['name' => 'Backend']);
    $php = Label::create(['name' => 'PHP']);

    LabelRelationship::create([
        'parent_label_id' => $tech->id,
        'child_label_id' => $backend->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $backend->id,
        'child_label_id' => $php->id,
    ]);

    $route = LabelRoute::where('path', 'tech.backend.php')->first();
    $labels = $route->labels();

    expect($labels->count())->toBe(3);
    expect($labels->first()->slug)->toBe('tech');
    expect($labels->skip(1)->first()->slug)->toBe('backend');
    expect($labels->last()->slug)->toBe('php');
});

it('returns segments as array', function (): void {
    Label::create(['name' => 'Tech']);
    Label::create(['name' => 'Backend']);
    Label::create(['name' => 'PHP']);

    $tech = Label::where('slug', 'tech')->first();
    $backend = Label::where('slug', 'backend')->first();
    $php = Label::where('slug', 'php')->first();

    LabelRelationship::create([
        'parent_label_id' => $tech->id,
        'child_label_id' => $backend->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $backend->id,
        'child_label_id' => $php->id,
    ]);

    $route = LabelRoute::where('path', 'tech.backend.php')->first();

    expect($route->segments)->toBe(['tech', 'backend', 'php']);
});
