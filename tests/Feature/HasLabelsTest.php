<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\InvalidRouteException;
use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Tests\Fixtures\Ticket;

beforeEach(function (): void {
    // Create a basic label hierarchy: Tech -> Backend -> PHP
    $this->tech = Label::create(['name' => 'Tech']);
    $this->backend = Label::create(['name' => 'Backend']);
    $this->php = Label::create(['name' => 'PHP']);

    LabelRelationship::create([
        'parent_label_id' => $this->tech->id,
        'child_label_id' => $this->backend->id,
    ]);
    LabelRelationship::create([
        'parent_label_id' => $this->backend->id,
        'child_label_id' => $this->php->id,
    ]);
});

it('attaches route by path string', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);

    $ticket->attachRoute('tech.backend');

    expect($ticket->labelRoutes)->toHaveCount(1);
    expect($ticket->labelRoutes->first()->path)->toBe('tech.backend');
});

it('attaches route by model', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $route = LabelRoute::where('path', 'tech.backend.php')->first();

    $ticket->attachRoute($route);

    expect($ticket->labelRoutes)->toHaveCount(1);
    expect($ticket->labelRoutes->first()->path)->toBe('tech.backend.php');
});

it('throws exception when attaching non-existent route', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);

    $ticket->attachRoute('nonexistent.path');
})->throws(InvalidRouteException::class, 'Route not found: nonexistent.path');

it('detaches route by path string', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend');

    expect($ticket->labelRoutes)->toHaveCount(1);

    $ticket->detachRoute('tech.backend');

    $ticket->refresh();
    expect($ticket->labelRoutes)->toHaveCount(0);
});

it('detaches route by model', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $route = LabelRoute::where('path', 'tech.backend')->first();
    $ticket->attachRoute($route);

    $ticket->detachRoute($route);

    $ticket->refresh();
    expect($ticket->labelRoutes)->toHaveCount(0);
});

it('syncs routes replacing all', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech');
    $ticket->attachRoute('backend');

    expect($ticket->labelRoutes)->toHaveCount(2);

    $ticket->syncRoutes(['tech.backend', 'tech.backend.php']);

    $ticket->refresh();
    expect($ticket->labelRoutes)->toHaveCount(2);
    expect($ticket->label_paths)->toContain('tech.backend');
    expect($ticket->label_paths)->toContain('tech.backend.php');
    expect($ticket->label_paths)->not->toContain('tech');
    expect($ticket->label_paths)->not->toContain('backend');
});

it('checks if route is attached', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend');

    expect($ticket->hasRoute('tech.backend'))->toBeTrue();
    expect($ticket->hasRoute('tech'))->toBeFalse();
});

it('checks if route matching pattern is attached', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    // In lquery, * matches zero or more labels
    // tech.* matches tech followed by any number of labels (including zero)
    expect($ticket->hasRouteMatching('tech.*'))->toBeTrue();
    // *.php matches any path ending with php
    expect($ticket->hasRouteMatching('*.php'))->toBeTrue();
    // frontend.* won't match because path starts with tech
    expect($ticket->hasRouteMatching('frontend.*'))->toBeFalse();
});

it('returns attached route paths via accessor', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech');
    $ticket->attachRoute('tech.backend');

    $paths = $ticket->label_paths;

    expect($paths)->toContain('tech');
    expect($paths)->toContain('tech.backend');
});

it('does not duplicate attachments on multiple calls', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend');
    $ticket->attachRoute('tech.backend');
    $ticket->attachRoute('tech.backend');

    expect($ticket->labelRoutes)->toHaveCount(1);
});

it('queries models by exact route path', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);
    $ticket3 = Ticket::create(['title' => 'Ticket 3']);

    $ticket1->attachRoute('tech');
    $ticket2->attachRoute('tech.backend');
    $ticket3->attachRoute('tech.backend.php');

    $results = Ticket::whereHasRoute('tech.backend')->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->title)->toBe('Ticket 2');
});

it('queries models by route pattern', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);
    $ticket3 = Ticket::create(['title' => 'Ticket 3']);

    $ticket1->attachRoute('tech');
    $ticket2->attachRoute('tech.backend');
    $ticket3->attachRoute('backend');

    // tech.* matches "tech" and anything under it (zero or more labels after tech)
    $results = Ticket::whereHasRouteMatching('tech.*')->get();

    expect($results)->toHaveCount(2);
    $titles = $results->pluck('title')->toArray();
    expect($titles)->toContain('Ticket 1');
    expect($titles)->toContain('Ticket 2');
});

it('queries models with routes descending from path', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);
    $ticket3 = Ticket::create(['title' => 'Ticket 3']);

    $ticket1->attachRoute('tech');
    $ticket2->attachRoute('tech.backend');
    $ticket3->attachRoute('tech.backend.php');

    $results = Ticket::whereHasRouteDescendantOf('tech')->get();

    expect($results)->toHaveCount(2);
    $titles = $results->pluck('title')->toArray();
    expect($titles)->toContain('Ticket 2');
    expect($titles)->toContain('Ticket 3');
});

it('queries models with routes that are ancestors of path', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);
    $ticket3 = Ticket::create(['title' => 'Ticket 3']);

    $ticket1->attachRoute('tech');
    $ticket2->attachRoute('tech.backend');
    $ticket3->attachRoute('tech.backend.php');

    $results = Ticket::whereHasRouteAncestorOf('tech.backend.php')->get();

    expect($results)->toHaveCount(2);
    $titles = $results->pluck('title')->toArray();
    expect($titles)->toContain('Ticket 1');
    expect($titles)->toContain('Ticket 2');
});

it('eager loads routes count', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech');
    $ticket->attachRoute('tech.backend');

    $loaded = Ticket::withRoutesCount()->find($ticket->id);

    expect($loaded->label_routes_count)->toBe(2);
});

it('eager loads routes', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech');
    $ticket->attachRoute('tech.backend');

    $loaded = Ticket::withRoutes()->find($ticket->id);

    expect($loaded->relationLoaded('labelRoutes'))->toBeTrue();
    expect($loaded->labelRoutes)->toHaveCount(2);
});
