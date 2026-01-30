# Tinker Testing Checklist

Manual testing guide for all Laravel Label Tree package operations.

## Prerequisites

### Database Setup

Ensure your database is running and configured. By default, workbench uses SQLite.

```bash
# Run migrations
./vendor/bin/testbench migrate

# Or with fresh data
./vendor/bin/testbench migrate:fresh
```

### Start Tinker

```bash
./vendor/bin/testbench tinker
```

## 1. Label CRUD Operations

### Create Label

```php
use Birdcar\LabelTree\Models\Label;

$label = Label::create(['name' => 'Bug']);
// Expected: Label model with ULID id, slug 'bug', color null

$label = Label::create(['name' => 'Feature Request', 'color' => '#10b981']);
// Expected: Label with slug 'feature-request', color '#10b981'
```

### Read Labels

```php
Label::all();
// Expected: Collection with 2 labels

Label::find($label->id);
// Expected: Single Label model

Label::where('slug', 'bug')->first();
// Expected: Bug label
```

### Update Label

```php
$label = Label::where('slug', 'bug')->first();
$label->update(['name' => 'Bug Report', 'color' => '#ef4444']);
// Expected: slug remains 'bug', name/color updated
```

### Delete Label

```php
$label = Label::where('slug', 'bug')->first();
$label->delete();
// Expected: Label deleted, any child relationships removed
```

## 2. Relationship CRUD Operations

### Setup Test Labels

```php
use Birdcar\LabelTree\Models\Label;

$status = Label::create(['name' => 'Status']);
$open = Label::create(['name' => 'Open']);
$closed = Label::create(['name' => 'Closed']);
```

### Create Relationship

```php
use Birdcar\LabelTree\Models\LabelRelationship;

LabelRelationship::create([
    'parent_label_id' => $status->id,
    'child_label_id' => $open->id,
]);
// Expected: Relationship created, routes auto-generated

LabelRelationship::create([
    'parent_label_id' => $status->id,
    'child_label_id' => $closed->id,
]);
// Expected: Second relationship, new routes generated
```

### Read Relationships

```php
// Get parent's children
$status->children;
// Expected: Collection with Open, Closed

// Get child's parents
$open->parents;
// Expected: Collection with Status

// Get all relationships
LabelRelationship::all();
// Expected: Collection with 2 relationships
```

### Delete Relationship

```php
$relationship = LabelRelationship::where('parent_label_id', $status->id)
    ->where('child_label_id', $open->id)
    ->first();
$relationship->delete();
// Expected: Relationship removed, routes regenerated
```

## 3. Route Generation

### View Routes

```php
use Birdcar\LabelTree\Models\LabelRoute;

// After creating relationships, routes are auto-generated
LabelRoute::all();
// Expected: Routes for each label's path(s)

// Get routes containing a specific label slug
LabelRoute::where('path', 'LIKE', '%open%')->get();
// Expected: Collection with route(s) like 'status.open'

// Get exact route
LabelRoute::where('path', 'status.open')->first();
// Expected: Single route with path 'status.open'

// View all route paths
LabelRoute::pluck('path');
// Expected: Collection of path strings
```

### Multiple Paths (DAG)

```php
// Create a label with multiple parents
$type = Label::create(['name' => 'Type']);
$area = Label::create(['name' => 'Area']);
$api = Label::create(['name' => 'API']);
$bug = Label::create(['name' => 'Bug']);
$apiBug = Label::create(['name' => 'API Bug']);

// Make API child of Area
LabelRelationship::create(['parent_label_id' => $area->id, 'child_label_id' => $api->id]);

// Make Bug child of Type
LabelRelationship::create(['parent_label_id' => $type->id, 'child_label_id' => $bug->id]);

// Make API Bug child of BOTH API and Bug (DAG)
LabelRelationship::create(['parent_label_id' => $api->id, 'child_label_id' => $apiBug->id]);
LabelRelationship::create(['parent_label_id' => $bug->id, 'child_label_id' => $apiBug->id]);

// Check routes for API Bug (has two paths via different parents)
LabelRoute::where('path', 'LIKE', '%api-bug')->pluck('path');
// Expected: ['area.api.api-bug', 'type.bug.api-bug'] (two paths!)
```

## 4. Cycle Detection

### Attempt Invalid Cycle

```php
$a = Label::create(['name' => 'Label A']);
$b = Label::create(['name' => 'Label B']);
$c = Label::create(['name' => 'Label C']);

// Valid chain: A -> B -> C
LabelRelationship::create(['parent_label_id' => $a->id, 'child_label_id' => $b->id]);
LabelRelationship::create(['parent_label_id' => $b->id, 'child_label_id' => $c->id]);

// Attempt cycle: C -> A (would create A -> B -> C -> A)
try {
    LabelRelationship::create(['parent_label_id' => $c->id, 'child_label_id' => $a->id]);
} catch (\Illuminate\Validation\ValidationException $e) {
    echo $e->getMessage();
}
// Expected: ValidationException - cycle detected, relationship NOT created

// Verify no cycle was created
LabelRelationship::where('parent_label_id', $c->id)->where('child_label_id', $a->id)->exists();
// Expected: false
```

## 5. Pattern Query Examples

### Using Query Builder

```php
use Birdcar\LabelTree\Query\LabelQuery;

// Create test data
$status = Label::create(['name' => 'Status']);
$open = Label::create(['name' => 'Open']);
$closed = Label::create(['name' => 'Closed']);
LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $open->id]);
LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $closed->id]);

// Query by exact path
LabelQuery::matching('status.open')->get();
// Expected: Collection with Open label

// Wildcard patterns
LabelQuery::matching('status.*')->get();
// Expected: Collection with Open and Closed

// Find all descendants
LabelQuery::matching('status.**')->get();
// Expected: All labels under Status (any depth)

// Negation
LabelQuery::matching('status.!open')->get();
// Expected: Closed only
```

### Artisan Commands

```bash
# List all labels
./vendor/bin/testbench label:list

# List with tree structure
./vendor/bin/testbench label:list --tree

# Query by pattern
./vendor/bin/testbench label:query 'status.*'
```

## 6. Safe Deletion Behavior

### Test Delete with Children

```php
$parent = Label::create(['name' => 'Parent']);
$child = Label::create(['name' => 'Child']);
LabelRelationship::create(['parent_label_id' => $parent->id, 'child_label_id' => $child->id]);

// Attempt to delete parent
$parent->delete();

// Check: child should still exist, relationship removed
Label::where('slug', 'child')->exists();
// Expected: true

LabelRelationship::where('parent_label_id', $parent->id)->exists();
// Expected: false
```

## 7. HasLabels Trait (Polymorphic Attachments)

The HasLabels trait attaches **routes** (paths) to models, not labels directly. This allows tracking which hierarchical path a model is associated with.

### Attach Routes to Model

```php
use Workbench\App\Models\Ticket;
use Birdcar\LabelTree\Models\LabelRoute;

// First, create labels and relationships (or use seeded data)
$status = Label::create(['name' => 'Status']);
$open = Label::create(['name' => 'Open']);
LabelRelationship::create(['parent_label_id' => $status->id, 'child_label_id' => $open->id]);

// Create a ticket
$ticket = Ticket::create([
    'title' => 'Login button broken',
    'description' => 'Cannot click login on mobile',
]);

// Attach routes by path string
$ticket->attachRoute('status.open');
$ticket->attachRoute('open'); // Root label route

// Or attach by LabelRoute model
$bugRoute = LabelRoute::where('path', 'type.bug')->first();
$ticket->attachRoute($bugRoute);

// Read attached routes
$ticket->labelRoutes;
// Expected: Collection of LabelRoute models

// Get route paths as array
$ticket->label_paths;
// Expected: ['status.open', 'open', 'type.bug']
```

### Check Route Attachments

```php
// Check if specific route is attached
$ticket->hasRoute('status.open');
// Expected: true

$ticket->hasRoute('status.closed');
// Expected: false

// Check if any route matches a pattern
$ticket->hasRouteMatching('status.*');
// Expected: true
```

### Detach Routes

```php
$ticket->detachRoute('status.open');

$ticket->labelRoutes;
// Expected: Collection without status.open
```

### Sync Routes (Replace All)

```php
$ticket->syncRoutes(['status.open', 'type.bug', 'priority.high']);

$ticket->label_paths;
// Expected: ['status.open', 'type.bug', 'priority.high']
```

### Query Models by Routes

```php
// Find tickets with exact route attached
Ticket::whereHasRoute('status.open')->get();
// Expected: Collection of tickets with status.open route

// Find tickets with routes matching pattern
Ticket::whereHasRouteMatching('status.*')->get();
// Expected: Tickets with any status child route

// Find tickets with routes descending from path
Ticket::whereHasRouteDescendantOf('type')->get();
// Expected: Tickets with any route under 'type'

// Find tickets with routes that are ancestors of path
Ticket::whereHasRouteAncestorOf('type.bug.api-bug')->get();
// Expected: Tickets with 'type', 'type.bug', etc.

// Eager load routes
Ticket::withRoutes()->get();
// Expected: Tickets with labelRoutes relationship loaded

// Eager load routes count
Ticket::withRoutesCount()->get();
// Each ticket will have label_routes_count attribute
```

## 8. Database Switching (Herd)

The workbench `.env` file isn't always picked up by testbench commands. Use environment variables directly:

### Switch to PostgreSQL

```bash
# Set environment and run commands
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench db:wipe --database=pgsql --force

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench migrate --database=pgsql

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench db:seed --database=pgsql --class='Workbench\Database\Seeders\DatabaseSeeder'
```

### Switch to MySQL/MariaDB

```bash
# Set environment and run commands
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench db:wipe --database=mysql --force

DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench migrate --database=mysql

DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=laravel DB_USERNAME=root DB_PASSWORD= \
  ./vendor/bin/testbench db:seed --database=mysql --class='Workbench\Database\Seeders\DatabaseSeeder'
```

### Switch to SQLite (default)

```bash
./vendor/bin/testbench db:wipe --database=sqlite
./vendor/bin/testbench migrate --database=sqlite
./vendor/bin/testbench db:seed --database=sqlite --class='Workbench\Database\Seeders\DatabaseSeeder'
```

## 9. Seeded Data Testing

### Run Seeders

```bash
./vendor/bin/testbench migrate:fresh --seed
```

### Verify Seeded Data

```php
use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Models\LabelRoute;

// Check label count
Label::count();
// Expected: 20 (4 roots + 15 children + 1 DAG demo)
// - 4 roots: Status, Priority, Type, Area
// - Status children: Open, In Progress, Closed (3)
// - Priority children: Low, Medium, High, Critical (4)
// - Type children: Bug, Feature, Documentation, Chore (4)
// - Area children: Frontend, Backend, API, Database (4)
// - DAG: API Bug (1)

// Check relationship count
LabelRelationship::count();
// Expected: 17 (3 + 4 + 4 + 4 + 2 for API Bug's two parents)

// Check routes exist
LabelRoute::count();
// Expected: Routes for all label paths (varies based on hierarchy)

// Verify DAG: API Bug has two paths
LabelRoute::where('path', 'LIKE', '%api-bug')->pluck('path');
// Expected: Two paths - one through Type>Bug, one through Area>API
```

### Verify Tickets

```php
use Workbench\App\Models\Ticket;

Ticket::count();
// Expected: 8 seeded tickets

$ticket = Ticket::first();
$ticket->labelRoutes;
// Expected: Collection of attached routes

$ticket->label_paths;
// Expected: Array of path strings like ['status.open', 'type.bug', ...]
```

## Troubleshooting

### Database Not Running

```
SQLSTATE[HY000] [2002] Connection refused
```

Solution: Start the database service in Herd or your local setup.

### Missing Migrations

```
SQLSTATE[HY000]: General error: 1 no such table: labels
```

Solution: Run `./vendor/bin/testbench migrate`

### Duplicate Slug Error

Slugs are auto-generated from names. If you get a unique constraint violation, either use a different name or delete the existing label first.
