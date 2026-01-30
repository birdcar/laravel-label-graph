<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\CycleDetectedException;
use Birdcar\LabelTree\Exceptions\SelfReferenceException;
use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;

it('creates a relationship between two labels', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    $relationship = LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    expect($relationship)->toBeInstanceOf(LabelRelationship::class);
    expect($relationship->parent_label_id)->toBe($parent->id);
    expect($relationship->child_label_id)->toBe($child->id);
});

it('has parent and child accessors', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    $relationship = LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    expect($relationship->parent->id)->toBe($parent->id);
    expect($relationship->child->id)->toBe($child->id);
});

it('prevents self-referential relationships', function (): void {
    $label = Label::create(['name' => 'Self']);

    LabelRelationship::create([
        'parent_label_id' => $label->id,
        'child_label_id' => $label->id,
    ]);
})->throws(SelfReferenceException::class, 'Cannot create self-referential relationship');

it('prevents creating a direct cycle', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    // A -> B
    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    // B -> A would create cycle
    LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $a->id,
    ]);
})->throws(CycleDetectedException::class, 'Creating this relationship would form a cycle');

it('prevents creating an indirect cycle', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);

    // A -> B -> C
    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $c->id,
    ]);

    // C -> A would create cycle
    LabelRelationship::create([
        'parent_label_id' => $c->id,
        'child_label_id' => $a->id,
    ]);
})->throws(CycleDetectedException::class);

it('allows diamond pattern (multiple paths, no cycle)', function (): void {
    $root = Label::create(['name' => 'Root']);
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $leaf = Label::create(['name' => 'Leaf']);

    // Root -> A, Root -> B
    LabelRelationship::create([
        'parent_label_id' => $root->id,
        'child_label_id' => $a->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $root->id,
        'child_label_id' => $b->id,
    ]);

    // A -> Leaf, B -> Leaf (diamond pattern)
    LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $leaf->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $leaf->id,
    ]);

    expect(LabelRelationship::count())->toBe(4);
});

it('prevents duplicate relationships', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);
})->throws(\Illuminate\Database\QueryException::class)
    ->skip(fn () => usingPostgres(), 'PostgreSQL aborts transactions on constraint violations');

it('cascades deletion when parent label is deleted', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    expect(LabelRelationship::count())->toBe(1);

    $parent->delete();

    expect(LabelRelationship::count())->toBe(0);
});

it('cascades deletion when child label is deleted', function (): void {
    $parent = Label::create(['name' => 'Parent']);
    $child = Label::create(['name' => 'Child']);

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);

    expect(LabelRelationship::count())->toBe(1);

    $child->delete();

    expect(LabelRelationship::count())->toBe(0);
});
