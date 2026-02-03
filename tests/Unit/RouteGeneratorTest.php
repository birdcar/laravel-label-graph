<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Services\RouteGenerator;

beforeEach(function (): void {
    $this->generator = app(RouteGenerator::class);
});

it('generates single-label routes for each label', function (): void {
    Label::create(['name' => 'Tech']);
    Label::create(['name' => 'Design']);

    $this->generator->generateAll();

    $routes = LabelRoute::pluck('path')->toArray();

    expect($routes)->toContain('tech');
    expect($routes)->toContain('design');
});

it('generates multi-segment routes for relationships', function (): void {
    $parent = Label::create(['name' => 'Tech']);
    $child = Label::create(['name' => 'Backend']);

    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]));

    $this->generator->generateAll();

    $routes = LabelRoute::pluck('path')->toArray();

    expect($routes)->toContain('tech');
    expect($routes)->toContain('backend');
    expect($routes)->toContain('tech.backend');
});

it('handles diamond patterns correctly', function (): void {
    $root = Label::create(['name' => 'Root']);
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $leaf = Label::create(['name' => 'Leaf']);

    // Root -> A -> Leaf
    // Root -> B -> Leaf
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $root->id,
        'child_label_id' => $a->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $root->id,
        'child_label_id' => $b->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $leaf->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $leaf->id,
    ]));

    $this->generator->generateAll();

    $routes = LabelRoute::pluck('path')->toArray();

    // Single label routes
    expect($routes)->toContain('root');
    expect($routes)->toContain('a');
    expect($routes)->toContain('b');
    expect($routes)->toContain('leaf');

    // Two-segment routes
    expect($routes)->toContain('root.a');
    expect($routes)->toContain('root.b');
    expect($routes)->toContain('a.leaf');
    expect($routes)->toContain('b.leaf');

    // Three-segment routes (both paths through diamond)
    expect($routes)->toContain('root.a.leaf');
    expect($routes)->toContain('root.b.leaf');
});

it('is idempotent', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));

    $this->generator->generateAll();
    $firstCount = LabelRoute::count();
    $firstRoutes = LabelRoute::pluck('path')->sort()->values()->toArray();

    $this->generator->generateAll();
    $secondCount = LabelRoute::count();
    $secondRoutes = LabelRoute::pluck('path')->sort()->values()->toArray();

    expect($secondCount)->toBe($firstCount);
    expect($secondRoutes)->toBe($firstRoutes);
});

it('calculates depth correctly', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);

    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $c->id,
    ]));

    $this->generator->generateAll();

    expect(LabelRoute::where('path', 'a')->first()->depth)->toBe(0);
    expect(LabelRoute::where('path', 'a.b')->first()->depth)->toBe(1);
    expect(LabelRoute::where('path', 'a.b.c')->first()->depth)->toBe(2);
});

it('removes old routes when relationships are deleted', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));

    $this->generator->generateAll();

    expect(LabelRoute::where('path', 'a.b')->exists())->toBeTrue();

    // Delete the relationship
    LabelRelationship::truncate();

    $this->generator->generateAll();

    expect(LabelRoute::where('path', 'a.b')->exists())->toBeFalse();
    expect(LabelRoute::where('path', 'a')->exists())->toBeTrue();
    expect(LabelRoute::where('path', 'b')->exists())->toBeTrue();
});
