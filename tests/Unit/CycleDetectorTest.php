<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Services\CycleDetector;

beforeEach(function (): void {
    $this->detector = app(CycleDetector::class);
});

it('allows creating a valid relationship', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    $relationship = new LabelRelationship([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]);

    expect($this->detector->wouldCreateCycle($relationship))->toBeFalse();
});

it('detects a simple two-node cycle', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);

    // A -> B exists
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));

    // B -> A would create cycle
    $relationship = new LabelRelationship([
        'parent_label_id' => $b->id,
        'child_label_id' => $a->id,
    ]);

    expect($this->detector->wouldCreateCycle($relationship))->toBeTrue();
});

it('detects a three-node cycle', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);

    // A -> B -> C exists
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $c->id,
    ]));

    // C -> A would create cycle
    $relationship = new LabelRelationship([
        'parent_label_id' => $c->id,
        'child_label_id' => $a->id,
    ]);

    expect($this->detector->wouldCreateCycle($relationship))->toBeTrue();
});

it('allows a valid DAG with multiple paths', function (): void {
    // Diamond pattern: A -> B, A -> C, B -> D, C -> D
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);
    $d = Label::create(['name' => 'D']);

    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $c->id,
    ]));
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $b->id,
        'child_label_id' => $d->id,
    ]));

    // C -> D is valid (diamond)
    $relationship = new LabelRelationship([
        'parent_label_id' => $c->id,
        'child_label_id' => $d->id,
    ]);

    expect($this->detector->wouldCreateCycle($relationship))->toBeFalse();
});

it('allows unconnected labels', function (): void {
    $a = Label::create(['name' => 'A']);
    $b = Label::create(['name' => 'B']);
    $c = Label::create(['name' => 'C']);
    $d = Label::create(['name' => 'D']);

    // A -> B exists (separate from C, D)
    LabelRelationship::withoutEvents(fn () => LabelRelationship::create([
        'parent_label_id' => $a->id,
        'child_label_id' => $b->id,
    ]));

    // C -> D is valid (disconnected)
    $relationship = new LabelRelationship([
        'parent_label_id' => $c->id,
        'child_label_id' => $d->id,
    ]);

    expect($this->detector->wouldCreateCycle($relationship))->toBeFalse();
});
