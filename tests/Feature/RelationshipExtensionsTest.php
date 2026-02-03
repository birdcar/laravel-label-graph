<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Tests\Fixtures\Ticket;

beforeEach(function (): void {
    // Create hierarchy via labels and relationships
    $electronics = Label::create(['name' => 'Electronics']);
    $phones = Label::create(['name' => 'Phones']);
    $iphone = Label::create(['name' => 'iPhone']);
    $android = Label::create(['name' => 'Android']);
    $computers = Label::create(['name' => 'Computers']);

    LabelRelationship::create([
        'parent_label_id' => $electronics->id,
        'child_label_id' => $phones->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $phones->id,
        'child_label_id' => $iphone->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $phones->id,
        'child_label_id' => $android->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $electronics->id,
        'child_label_id' => $computers->id,
    ]);

    // Create tickets with different routes
    $ticket1 = Ticket::create(['title' => 'iPhone Bug']);
    $ticket1->attachRoute('electronics.phones.iphone');

    $ticket2 = Ticket::create(['title' => 'Android Bug']);
    $ticket2->attachRoute('electronics.phones.android');

    $ticket3 = Ticket::create(['title' => 'General Phone Bug']);
    $ticket3->attachRoute('electronics.phones');

    $ticket4 = Ticket::create(['title' => 'Computer Bug']);
    $ticket4->attachRoute('electronics.computers');
});

describe('labelablesOfDescendants', function (): void {
    it('returns models from descendant routes only', function (): void {
        $phones = LabelRoute::where('path', 'electronics.phones')->first();

        $tickets = $phones->labelablesOfDescendants(Ticket::class)->get();

        expect($tickets)->toHaveCount(2);
        expect($tickets->pluck('title')->toArray())->toContain(
            'iPhone Bug',
            'Android Bug'
        );
        expect($tickets->pluck('title')->toArray())->not->toContain('General Phone Bug');
    });

    it('returns empty for leaf routes', function (): void {
        $iphone = LabelRoute::where('path', 'electronics.phones.iphone')->first();

        $tickets = $iphone->labelablesOfDescendants(Ticket::class)->get();

        expect($tickets)->toHaveCount(0);
    });
});

describe('labelablesOfDescendantsAndSelf', function (): void {
    it('includes models from current route', function (): void {
        $phones = LabelRoute::where('path', 'electronics.phones')->first();

        $tickets = $phones->labelablesOfDescendantsAndSelf(Ticket::class)->get();

        expect($tickets)->toHaveCount(3);
        expect($tickets->pluck('title')->toArray())->toContain(
            'iPhone Bug',
            'Android Bug',
            'General Phone Bug'
        );
    });
});

describe('labelablesOfDescendantsCount', function (): void {
    it('returns correct count', function (): void {
        $electronics = LabelRoute::where('path', 'electronics')->first();

        $count = $electronics->labelablesOfDescendantsCount(Ticket::class);

        expect($count)->toBe(4); // All tickets are under electronics
    });
});

describe('hasLabelablesInDescendants', function (): void {
    it('returns true when labelables exist', function (): void {
        $phones = LabelRoute::where('path', 'electronics.phones')->first();

        expect($phones->hasLabelablesInDescendants(Ticket::class))->toBeTrue();
    });

    it('returns false for empty subtrees', function (): void {
        // Create empty branch
        $empty = Label::create(['name' => 'Empty']);
        LabelRelationship::create([
            'parent_label_id' => Label::where('name', 'Electronics')->first()->id,
            'child_label_id' => $empty->id,
        ]);

        $emptyRoute = LabelRoute::where('path', 'electronics.empty')->first();

        expect($emptyRoute->hasLabelablesInDescendants(Ticket::class))->toBeFalse();
    });
});

describe('whereHasRouteOrDescendant scope', function (): void {
    it('finds models with route or any descendant', function (): void {
        $tickets = Ticket::whereHasRouteOrDescendant('electronics.phones')->get();

        expect($tickets)->toHaveCount(3);
        expect($tickets->pluck('title')->toArray())->toContain(
            'iPhone Bug',
            'Android Bug',
            'General Phone Bug'
        );
    });

    it('works with root routes', function (): void {
        $tickets = Ticket::whereHasRouteOrDescendant('electronics')->get();

        expect($tickets)->toHaveCount(4); // All tickets
    });
});

describe('whereHasRouteOrAncestor scope', function (): void {
    it('finds models with route or any ancestor', function (): void {
        $tickets = Ticket::whereHasRouteOrAncestor('electronics.phones.iphone')->get();

        // Should find: iPhone Bug (exact), General Phone Bug (ancestor phones),
        // but NOT Computer Bug (different branch)
        expect($tickets)->toHaveCount(2);
        expect($tickets->pluck('title')->toArray())->toContain(
            'iPhone Bug',
            'General Phone Bug'
        );
    });
});

describe('whereHasRouteInSubtrees scope', function (): void {
    it('finds models in multiple subtrees', function (): void {
        $tickets = Ticket::whereHasRouteInSubtrees([
            'electronics.phones.iphone',
            'electronics.computers',
        ])->get();

        expect($tickets)->toHaveCount(2);
        expect($tickets->pluck('title')->toArray())->toContain(
            'iPhone Bug',
            'Computer Bug'
        );
    });
});
