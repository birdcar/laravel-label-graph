# Laravel Label Graph: Implementation Brief

> Complete implementation guide for AI coding assistants.
> Follow steps sequentially for a working label system.
> All code is copy-paste ready with concrete examples.

## Prerequisites

- Laravel 11.0+ application
- PHP 8.3+
- Database: SQLite, MySQL 8+, or PostgreSQL 14+

---

## Step 1: Install Package

```bash
# Install via Composer
composer require birdcar/laravel-label-graph

# Publish and run migrations
php artisan vendor:publish --tag=label-graph-migrations
php artisan migrate
```

Optionally publish config:
```bash
php artisan vendor:publish --tag=label-graph-config
```

For PostgreSQL, install ltree extension for better performance:
```bash
php artisan label-graph:install-ltree
```

---

## Step 2: Add HasLabels Trait to Your Model

```php
<?php
// app/Models/Ticket.php

namespace App\Models;

use Birdcar\LabelGraph\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasLabels;

    protected $fillable = ['title', 'description', 'status'];
}
```

The `HasLabels` trait adds:
- `labelRoutes` relationship (polymorphic many-to-many)
- `attachRoute()`, `detachRoute()`, `syncRoutes()` methods
- Query scopes: `whereHasRoute()`, `whereHasRouteMatching()`, `whereHasRouteDescendantOf()`

---

## Step 3: Create Label Hierarchy

Labels form a directed acyclic graph (DAG). Create labels first, then relationships.

```php
<?php

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;

// Create root labels (top-level categories)
$priority = Label::create(['name' => 'Priority']);
$type = Label::create(['name' => 'Type']);
$area = Label::create(['name' => 'Area']);

// Create child labels
$high = Label::create(['name' => 'High']);
$critical = Label::create(['name' => 'Critical']);
$bug = Label::create(['name' => 'Bug']);
$feature = Label::create(['name' => 'Feature']);
$frontend = Label::create(['name' => 'Frontend']);
$backend = Label::create(['name' => 'Backend']);

// Create hierarchy via relationships
// Priority hierarchy: priority → high → critical
LabelRelationship::create([
    'parent_label_id' => $priority->id,
    'child_label_id' => $high->id,
]);
LabelRelationship::create([
    'parent_label_id' => $high->id,
    'child_label_id' => $critical->id,
]);

// Type hierarchy: type → bug, type → feature
LabelRelationship::create([
    'parent_label_id' => $type->id,
    'child_label_id' => $bug->id,
]);
LabelRelationship::create([
    'parent_label_id' => $type->id,
    'child_label_id' => $feature->id,
]);

// Area hierarchy: area → frontend, area → backend
LabelRelationship::create([
    'parent_label_id' => $area->id,
    'child_label_id' => $frontend->id,
]);
LabelRelationship::create([
    'parent_label_id' => $area->id,
    'child_label_id' => $backend->id,
]);

// Routes are auto-generated:
// priority, priority.high, priority.high.critical
// type, type.bug, type.feature
// area, area.frontend, area.backend
```

---

## Step 4: Attach Labels to Models

```php
<?php

use App\Models\Ticket;

$ticket = Ticket::create([
    'title' => 'Fix login button',
    'description' => 'Login button not working on mobile',
    'status' => 'open',
]);

// Attach routes by path string
$ticket->attachRoute('priority.high.critical');
$ticket->attachRoute('type.bug');
$ticket->attachRoute('area.frontend');

// Sync routes (replaces all existing)
$ticket->syncRoutes([
    'priority.high',
    'type.feature',
    'area.backend',
]);

// Detach specific route
$ticket->detachRoute('priority.high');

// Check if route is attached
if ($ticket->hasRoute('type.bug')) {
    // Has the bug label
}

// Check by pattern
if ($ticket->hasRouteMatching('priority.*')) {
    // Has any priority label
}

// Get all attached routes
$routes = $ticket->labelRoutes; // Collection<LabelRoute>
$paths = $ticket->label_paths;  // ['type.feature', 'area.backend']
```

---

## Step 5: Query Models by Labels

```php
<?php

use App\Models\Ticket;

// Exact route match
$criticalTickets = Ticket::whereHasRoute('priority.high.critical')->get();

// Pattern matching (* = zero or more segments)
$allPriorityTickets = Ticket::whereHasRouteMatching('priority.*')->get();
$allBugTickets = Ticket::whereHasRouteMatching('type.bug')->get();
$anyBackend = Ticket::whereHasRouteMatching('*.backend')->get();

// Descendants of a path
$highPriorityAndBelow = Ticket::whereHasRouteDescendantOf('priority.high')->get();

// Ancestors of a path
$priorityAncestors = Ticket::whereHasRouteAncestorOf('priority.high.critical')->get();

// Combine scopes
$criticalBugs = Ticket::query()
    ->whereHasRouteMatching('priority.high.*')
    ->whereHasRoute('type.bug')
    ->get();

// Eager load routes
$tickets = Ticket::withRoutes()->get();
foreach ($tickets as $ticket) {
    echo implode(', ', $ticket->label_paths);
}

// With route count
$tickets = Ticket::withRoutesCount()->get();
foreach ($tickets as $ticket) {
    echo "Labels: {$ticket->label_routes_count}";
}
```

---

## Common Patterns

### Pattern A: E-commerce Product Categories

Products can appear in multiple categories (DAG advantage).

```php
<?php

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;

// Create category hierarchy
$electronics = Label::create(['name' => 'Electronics']);
$gaming = Label::create(['name' => 'Gaming']);
$mice = Label::create(['name' => 'Mice']);
$accessories = Label::create(['name' => 'Accessories']);

// Electronics → Mice
LabelRelationship::create([
    'parent_label_id' => $electronics->id,
    'child_label_id' => $mice->id,
]);

// Gaming → Accessories
LabelRelationship::create([
    'parent_label_id' => $gaming->id,
    'child_label_id' => $accessories->id,
]);

// Multi-parent: Mice also under Gaming (gaming mouse)
LabelRelationship::create([
    'parent_label_id' => $gaming->id,
    'child_label_id' => $mice->id,
]);

// Routes generated:
// electronics, electronics.mice
// gaming, gaming.accessories, gaming.mice

// Attach product to multiple categories
$product->attachRoute('electronics.mice');
$product->attachRoute('gaming.mice');

// Query: Find all gaming products
Product::whereHasRouteMatching('gaming.*')->get();

// Query: Find all mice (regardless of category)
Product::whereHasRouteMatching('*.mice')->get();
```

### Pattern B: Issue Tracker Labels

GitHub-style hierarchical labels with fast queries.

```php
<?php

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;

// Create label taxonomy
$priority = Label::create(['name' => 'Priority']);
$p0 = Label::create(['name' => 'P0', 'color' => '#ff0000']);
$p1 = Label::create(['name' => 'P1', 'color' => '#ff8800']);
$p2 = Label::create(['name' => 'P2', 'color' => '#ffff00']);

LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $p0->id]);
LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $p1->id]);
LabelRelationship::create(['parent_label_id' => $priority->id, 'child_label_id' => $p2->id]);

$status = Label::create(['name' => 'Status']);
$open = Label::create(['name' => 'Open']);
$inProgress = Label::create(['name' => 'In Progress']);
$closed = Label::create(['name' => 'Closed']);

LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $open->id]);
LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $inProgress->id]);
LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $closed->id]);

// Attach to issues
$issue->syncRoutes(['priority.p0', 'status.open']);

// Dashboard queries
$urgentOpen = Issue::whereHasRoute('priority.p0')
    ->whereHasRoute('status.open')
    ->get();

$allOpenIssues = Issue::whereHasRouteMatching('status.open')->get();
$allPriorityIssues = Issue::whereHasRouteMatching('priority.*')->get();
```

### Pattern C: Content Taxonomy

Blog/CMS with hierarchical topics.

```php
<?php

use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;

// Create topic hierarchy
$tech = Label::create(['name' => 'Technology']);
$programming = Label::create(['name' => 'Programming']);
$php = Label::create(['name' => 'PHP']);
$laravel = Label::create(['name' => 'Laravel']);

LabelRelationship::create(['parent_label_id' => $tech->id, 'child_label_id' => $programming->id]);
LabelRelationship::create(['parent_label_id' => $programming->id, 'child_label_id' => $php->id]);
LabelRelationship::create(['parent_label_id' => $php->id, 'child_label_id' => $laravel->id]);

// Route: technology.programming.php.laravel

// Attach at most specific level
$post->attachRoute('technology.programming.php.laravel');

// Query: All tech posts
Post::whereHasRouteMatching('technology.*')->get();

// Query: All PHP posts (including Laravel)
Post::whereHasRouteMatching('technology.programming.php.*')->get();

// Query: Just Laravel posts
Post::whereHasRoute('technology.programming.php.laravel')->get();
```

---

## Error Handling

```php
<?php

use Birdcar\LabelGraph\Exceptions\CycleDetectedException;
use Birdcar\LabelGraph\Exceptions\SelfReferenceException;
use Birdcar\LabelGraph\Exceptions\InvalidRouteException;

// Handle cycle detection
try {
    LabelRelationship::create([
        'parent_label_id' => $descendant->id,
        'child_label_id' => $ancestor->id, // Would create cycle
    ]);
} catch (CycleDetectedException $e) {
    // Log or notify: "Creating this relationship would form a cycle"
    return back()->withErrors(['relationship' => 'This would create a circular reference']);
}

// Handle self-reference
try {
    LabelRelationship::create([
        'parent_label_id' => $label->id,
        'child_label_id' => $label->id, // Same label
    ]);
} catch (SelfReferenceException $e) {
    return back()->withErrors(['relationship' => 'A label cannot be its own parent']);
}

// Handle invalid route
try {
    $ticket->attachRoute('nonexistent.path');
} catch (InvalidRouteException $e) {
    return back()->withErrors(['route' => "Route not found: {$e->getMessage()}"]);
}
```

---

## Quick Reference

```php
// === LABELS ===
Label::create(['name' => 'Name', 'color' => '#hex', 'icon' => 'icon-name']);

// === RELATIONSHIPS ===
LabelRelationship::create(['parent_label_id' => $parent->id, 'child_label_id' => $child->id]);

// === ATTACH/DETACH ===
$model->attachRoute('path.to.route');
$model->detachRoute('path.to.route');
$model->syncRoutes(['path.one', 'path.two']);

// === CHECK ===
$model->hasRoute('exact.path');
$model->hasRouteMatching('pattern.*');
$model->label_paths; // ['path.one', 'path.two']

// === QUERY ===
Model::whereHasRoute('exact.path')->get();
Model::whereHasRouteMatching('pattern.*')->get();
Model::whereHasRouteDescendantOf('path')->get();
Model::whereHasRouteAncestorOf('deep.path')->get();

// === ROUTES ===
LabelRoute::wherePathMatches('pattern.*')->get();
LabelRoute::whereDescendantOf('path')->get();
LabelRoute::whereAncestorOf('deep.path')->get();
LabelRoute::whereDepth(0)->get(); // Root routes only

// === CLI ===
php artisan label-graph:label:list
php artisan label-graph:label:create "Name"
php artisan label-graph:route:list
php artisan label-graph:route:regenerate
php artisan label-graph:validate
php artisan label-graph:visualize
```

---

*This brief contains everything needed to implement laravel-label-graph. No additional documentation required for basic usage.*
