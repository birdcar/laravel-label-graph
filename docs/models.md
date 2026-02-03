<!-- Keywords: Label model, LabelRelationship, LabelRoute, ULID labels, polymorphic labels -->

# Models

> Core models for hierarchical labels: Label (nodes), LabelRelationship (edges), LabelRoute (materialized paths).

Laravel Label Graph provides three main models:

## Label

The `Label` model represents a single label in your taxonomy.

```php
use Birdcar\LabelGraph\Models\Label;

$label = Label::create([
    'name' => 'Priority',
    'slug' => 'priority',     // Auto-generated from name if omitted
    'color' => '#ff0000',     // Optional
    'icon' => 'flag',         // Optional
    'description' => '...',   // Optional
]);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string (ULID) | Primary key |
| `name` | string | Display name |
| `slug` | string | URL-safe identifier (auto-generated) |
| `color` | string\|null | Optional color code |
| `icon` | string\|null | Optional icon identifier |
| `description` | string\|null | Optional description |

### Relationships

```php
// Get relationships where this label is the parent
$label->relationships();

// Get relationships where this label is the child
$label->reverseRelationships();
```

## LabelRelationship

The `LabelRelationship` model defines parent-child edges in the DAG.

```php
use Birdcar\LabelGraph\Models\LabelRelationship;

// Create a relationship (routes auto-regenerate)
$relationship = LabelRelationship::create([
    'parent_label_id' => $parent->id,
    'child_label_id' => $child->id,
]);
```

### Validation

Relationships are automatically validated on creation:

- **Self-reference check**: A label cannot be its own parent
- **Cycle detection**: Creating cycles (A → B → C → A) is rejected

```php
// This throws SelfReferenceException
LabelRelationship::create([
    'parent_label_id' => $label->id,
    'child_label_id' => $label->id,
]);

// This throws CycleDetectedException
LabelRelationship::create([
    'parent_label_id' => $descendant->id,
    'child_label_id' => $ancestor->id,
]);
```

### Deletion

Deleting relationships that would orphan routes with attachments is blocked:

```php
// Throws RoutesInUseException if models are attached to affected routes
$relationship->delete();

// Check if safe to delete
if ($relationship->canDelete()) {
    $relationship->delete();
}

// Get affected routes and attachment count
$affected = $relationship->getAffectedRoutes();
$count = $relationship->getAffectedAttachmentCount();

// Force delete with cascade (removes attachments)
$relationship->deleteAndCascade();

// Delete and migrate attachments to another route
$relationship->deleteAndReplace('alternative.route.path');
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string (ULID) | Primary key |
| `parent_label_id` | string | Foreign key to parent label |
| `child_label_id` | string | Foreign key to child label |

### Relationships

```php
$relationship->parent();  // Label
$relationship->child();   // Label
```

## LabelRoute

The `LabelRoute` model stores materialized paths through the DAG.

```php
use Birdcar\LabelGraph\Models\LabelRoute;

// Routes are auto-generated, but can be queried
$route = LabelRoute::where('path', 'priority.high.critical')->first();

// Get path segments
$route->segments;  // ['priority', 'high', 'critical']

// Get depth (0 = root)
$route->depth;  // 2
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | string (ULID) | Primary key |
| `path` | string | Dot-separated path (e.g., "priority.high") |
| `depth` | int | Number of ancestors (0 = root) |

### Computed Properties

```php
$route->segments;  // Array of path segments
```

### Navigation Methods

```php
// Get parent route
$route->parent();  // LabelRoute|null

// Get direct children
$route->children();  // Collection<LabelRoute>

// Get all ancestors
$route->ancestors();  // Collection<LabelRoute>

// Get all descendants
$route->descendants();  // Collection<LabelRoute>

// Get labels in this path
$route->labels();  // Collection<Label>
```

### Inspection Methods

```php
$route->isRoot();                    // true if depth === 0
$route->isLeaf();                    // true if no children
$route->isAncestorOf($other);        // true if this is ancestor
$route->isDescendantOf($other);      // true if this is descendant
```

### Attachment Methods

```php
// Get all models of a type attached to this route
$route->labelables(Ticket::class)->get();

// Get all attachments grouped by type
$route->allLabelables();

// Check if any models attached
$route->hasAttachments();

// Get attachment count
$route->attachmentCount();

// Migrate attachments between routes
LabelRoute::migrateAttachments('old.path', 'new.path');
```

For query scopes, see [Query Scopes](query-scopes.md).
