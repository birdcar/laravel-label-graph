<!-- Keywords: DAG vs tree, materialized path, cycle detection, route generation -->

# Architecture

> How laravel-label-graph uses DAG structure, materialized paths, and cycle detection for hierarchical labels.

## DAG vs Tree

Traditional hierarchical structures use trees where each node has exactly one parent. Label Tree uses a directed acyclic graph (DAG) where nodes can have multiple parents.

### Why DAG?

Consider a "Wireless Gaming Mouse" product:
- In a tree: Must choose ONE category (Electronics? Gaming?)
- In a DAG: Can belong to both "Electronics > Mice" AND "Gaming > Accessories"

### Cycle Prevention

The key constraint of a DAG is: no cycles. If A → B → C, then C cannot point back to A.

Label Tree uses depth-first search (DFS) during relationship creation to detect cycles:

```
Creating: Priority → High → Critical → Priority
           ↑___________________________|

DFS from "Priority" finds path back to "Priority"
→ CycleDetectedException thrown
→ Relationship rejected
```

## Data Model

### Labels Table

```
labels
├── id (ULID)
├── name
├── slug (unique, auto-generated)
├── color (nullable)
├── icon (nullable)
├── description (nullable)
└── timestamps
```

### Relationships Table

```
label_relationships
├── id (ULID)
├── parent_label_id → labels.id
├── child_label_id → labels.id
└── timestamps
```

Edges in the DAG. Each row represents one parent → child connection.

### Routes Table

```
label_routes
├── id (ULID)
├── path (unique, e.g., "priority.high.critical")
├── depth (integer, 0 = root)
└── timestamps
```

Materialized paths through the graph.

### Labelables Table

```
labelables
├── id (ULID)
├── label_route_id → label_routes.id
├── labelable_type (model class)
├── labelable_id (model ID)
└── timestamps
```

Polymorphic pivot connecting routes to your models.

## Materialized Paths

Each label's position in the graph is stored as "routes" - paths from root to the label.

```
Labels: Priority, High, Critical
Relationships: Priority → High → Critical

Generated Routes:
- priority
- priority.high
- priority.high.critical
```

### Why Materialized Paths?

| Operation | Adjacency List | Materialized Path |
|-----------|---------------|-------------------|
| Find ancestors | O(depth) queries | O(1) string parse |
| Find descendants | Recursive CTE | LIKE 'path.%' |
| Check ancestry | Multiple queries | String contains |

### Multiple Parents = Multiple Routes

When a label has multiple parents, it generates multiple routes:

```
Labels: Gaming, Electronics, Mouse
Relationships:
  Gaming → Mouse
  Electronics → Mouse

Routes:
- gaming
- electronics
- gaming.mouse
- electronics.mouse
```

The same `Mouse` label appears in two different paths.

### Route Regeneration

Routes are automatically regenerated when:
- A new relationship is created (via observer)
- A relationship is deleted (via model hook)
- `php artisan label-graph:route:regenerate` is run manually

Regeneration is transactional - if it fails, no routes are changed.

## Query Adapters

Label Tree supports pattern matching queries across databases using adapters:

- **PostgreSQL**: Native regex via `~` operator
- **MySQL**: `REGEXP` function
- **SQLite**: PHP regex fallback (for testing)

The adapter is automatically selected based on your database connection.

### Pattern Syntax

Patterns use PostgreSQL lquery-inspired syntax:

| Pattern | Meaning |
|---------|---------|
| `priority` | Exact match |
| `priority.*` | Priority and all descendants (zero or more labels after) |
| `*.high` | Any path ending with "high" |
| `*` | Zero or more labels (greedy) |
| `priority.high` | Exact two-segment path |

Examples:
- `type.bug.*` - Bug and all its descendants
- `*.critical` - Any path ending in critical
- `priority.*` - Priority and everything under it
- `area.frontend.*` - All frontend area labels and descendants

## Services

### CycleDetector

Uses DFS to check if creating a relationship would form a cycle:

```php
$detector = app(CycleDetector::class);
$wouldCycle = $detector->wouldCreateCycle($relationship);
```

### RouteGenerator

Builds materialized paths from the relationship graph:

```php
$generator = app(RouteGenerator::class);
$generator->regenerateAll();  // Full rebuild
$generator->generateForLabel($label);  // Single label
$generator->pruneForDeletedRelationship($relationship);  // Cleanup
```

### GraphValidator

Validates graph integrity:

```php
$validator = app(GraphValidator::class);
$issues = $validator->validate();
// Returns array of detected issues
```
