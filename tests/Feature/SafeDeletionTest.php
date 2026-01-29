<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\InvalidRouteException;
use Birdcar\LabelTree\Exceptions\RoutesInUseException;
use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;
use Birdcar\LabelTree\Tests\Fixtures\Ticket;

beforeEach(function (): void {
    // Create a basic label hierarchy: Tech -> Backend -> PHP
    $this->tech = Label::create(['name' => 'Tech']);
    $this->backend = Label::create(['name' => 'Backend']);
    $this->php = Label::create(['name' => 'PHP']);

    $this->techBackendRel = LabelRelationship::create([
        'parent_label_id' => $this->tech->id,
        'child_label_id' => $this->backend->id,
    ]);
    $this->backendPhpRel = LabelRelationship::create([
        'parent_label_id' => $this->backend->id,
        'child_label_id' => $this->php->id,
    ]);
});

it('allows deleting relationship with no attachments', function (): void {
    $this->backendPhpRel->delete();

    expect(LabelRelationship::find($this->backendPhpRel->id))->toBeNull();
    expect(LabelRoute::where('path', 'tech.backend.php')->exists())->toBeFalse();
    expect(LabelRoute::where('path', 'backend.php')->exists())->toBeFalse();
});

it('blocks deleting relationship when routes have attachments', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    $this->backendPhpRel->delete();
})->throws(RoutesInUseException::class);

it('reports attachment count when blocking deletion', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend.php');
    $ticket2->attachRoute('backend.php');

    expect($this->backendPhpRel->getAffectedAttachmentCount())->toBe(2);
    expect($this->backendPhpRel->canDelete())->toBeFalse();
});

it('reports affected routes', function (): void {
    $affected = $this->backendPhpRel->getAffectedRoutes();

    $paths = $affected->pluck('path')->toArray();
    expect($paths)->toContain('tech.backend.php');
    expect($paths)->toContain('backend.php');
    expect($paths)->not->toContain('tech.backend');
    expect($paths)->not->toContain('tech');
});

it('deletes and cascades attachments', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    expect($ticket->labelRoutes)->toHaveCount(1);

    $this->backendPhpRel->deleteAndCascade();

    expect(LabelRelationship::find($this->backendPhpRel->id))->toBeNull();
    expect(LabelRoute::where('path', 'tech.backend.php')->exists())->toBeFalse();

    $ticket->refresh();
    expect($ticket->labelRoutes)->toHaveCount(0);
});

it('deletes and replaces attachments to another route', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    $this->backendPhpRel->deleteAndReplace('tech.backend');

    expect(LabelRelationship::find($this->backendPhpRel->id))->toBeNull();
    expect(LabelRoute::where('path', 'tech.backend.php')->exists())->toBeFalse();

    $ticket->refresh();
    expect($ticket->labelRoutes)->toHaveCount(1);
    expect($ticket->labelRoutes->first()->path)->toBe('tech.backend');
});

it('throws exception when replacement route does not exist', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    $this->backendPhpRel->deleteAndReplace('nonexistent.path');
})->throws(InvalidRouteException::class, 'Replacement route not found: nonexistent.path');

it('handles multiple attachments on same route during cascade', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend.php');
    $ticket2->attachRoute('tech.backend.php');

    $this->backendPhpRel->deleteAndCascade();

    $ticket1->refresh();
    $ticket2->refresh();

    expect($ticket1->labelRoutes)->toHaveCount(0);
    expect($ticket2->labelRoutes)->toHaveCount(0);
});

it('handles multiple attachments during replacement', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend.php');
    $ticket2->attachRoute('backend.php');

    $this->backendPhpRel->deleteAndReplace('backend');

    $ticket1->refresh();
    $ticket2->refresh();

    expect($ticket1->labelRoutes->first()->path)->toBe('backend');
    expect($ticket2->labelRoutes->first()->path)->toBe('backend');
});

it('allows force delete ignoring attachments', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    // Force delete skips the attachment check
    $this->backendPhpRel->forceDelete();

    expect(LabelRelationship::find($this->backendPhpRel->id))->toBeNull();
});

it('checks LabelRoute hasAttachments method', function (): void {
    $route = LabelRoute::where('path', 'tech.backend.php')->first();

    expect($route->hasAttachments())->toBeFalse();

    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend.php');

    expect($route->hasAttachments())->toBeTrue();
});

it('checks LabelRoute attachmentCount method', function (): void {
    $route = LabelRoute::where('path', 'tech.backend.php')->first();

    expect($route->attachmentCount())->toBe(0);

    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend.php');
    $ticket2->attachRoute('tech.backend.php');

    expect($route->attachmentCount())->toBe(2);
});

it('migrates attachments between routes', function (): void {
    $ticket = Ticket::create(['title' => 'Test Ticket']);
    $ticket->attachRoute('tech.backend');

    $migrated = LabelRoute::migrateAttachments('tech.backend', 'tech');

    expect($migrated)->toBe(1);

    $ticket->refresh();
    expect($ticket->labelRoutes->first()->path)->toBe('tech');
});

it('throws exception when migrating from non-existent route', function (): void {
    LabelRoute::migrateAttachments('nonexistent.path', 'tech');
})->throws(InvalidRouteException::class, 'Source or target route not found');

it('throws exception when migrating to non-existent route', function (): void {
    LabelRoute::migrateAttachments('tech', 'nonexistent.path');
})->throws(InvalidRouteException::class, 'Source or target route not found');

it('retrieves all labelables grouped by type', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend');
    $ticket2->attachRoute('tech.backend');

    $route = LabelRoute::where('path', 'tech.backend')->first();
    $labelables = $route->allLabelables();

    expect($labelables)->toHaveKey(Ticket::class);
    expect($labelables[Ticket::class])->toHaveCount(2);
});

it('retrieves labelables of specific type', function (): void {
    $ticket1 = Ticket::create(['title' => 'Ticket 1']);
    $ticket2 = Ticket::create(['title' => 'Ticket 2']);

    $ticket1->attachRoute('tech.backend');
    $ticket2->attachRoute('tech.backend');

    $route = LabelRoute::where('path', 'tech.backend')->first();
    $tickets = $route->labelables(Ticket::class)->get();

    expect($tickets)->toHaveCount(2);
    $titles = $tickets->pluck('title')->toArray();
    expect($titles)->toContain('Ticket 1');
    expect($titles)->toContain('Ticket 2');
});
