<!-- Keywords: lquery Laravel, pattern matching labels, wherePathMatches, whereDescendantOf -->

# Query Scopes

> Query labels using PostgreSQL lquery-style patterns, ancestry traversal, and depth filtering.

LabelRoute provides powerful query scopes for finding routes by pattern, ancestry, and depth.

## Pattern Matching (lquery)

Use `wherePathMatches()` with lquery-style patterns:

```php
use Birdcar\LabelGraph\Models\LabelRoute;

// Match exact path
LabelRoute::wherePathMatches('priority.high')->get();

// Match path and all descendants (* = zero or more segments)
LabelRoute::wherePathMatches('priority.*')->get();

// Match any path ending with segment
LabelRoute::wherePathMatches('*.critical')->get();

// Match any path containing segment
LabelRoute::wherePathMatches('*.api.*')->get();
```

### Pattern Syntax

| Pattern | Meaning | Examples Matched |
|---------|---------|-----------------|
| `priority` | Exact match | `priority` |
| `priority.*` | Path and descendants | `priority`, `priority.high`, `priority.high.critical` |
| `*.high` | Any ending with "high" | `priority.high`, `area.high` |
| `*` | Zero or more segments | (greedy match) |
| `%` | Exactly one segment | Single segment wildcard |
| `{n}` | Exactly n segments | `*{3}` = exactly 3 segments |
| `{n,}` | n or more segments | `*{2,}` = 2+ segments |
| `{n,m}` | Between n and m segments | `*{1,3}` = 1-3 segments |
| `!label` | Negation | Match any except "label" |
| `a\|b` | Alternatives | Match "a" or "b" |

### LIKE Patterns

For simple prefix/suffix matching without lquery:

```php
// Starts with
LabelRoute::wherePathLike('priority%')->get();

// Ends with
LabelRoute::wherePathLike('%critical')->get();

// Contains
LabelRoute::wherePathLike('%api%')->get();
```

## Text Pattern Matching (ltxtquery)

Use `wherePathMatchesText()` for full-text-search-like boolean patterns that match labels regardless of position:

```php
// Match paths containing label "europe"
LabelRoute::wherePathMatchesText('europe')->get();

// Match paths containing BOTH labels
LabelRoute::wherePathMatchesText('europe & asia')->get();

// Match paths containing EITHER label
LabelRoute::wherePathMatchesText('europe | asia')->get();

// Match paths NOT containing label
LabelRoute::wherePathMatchesText('!africa')->get();

// Nested boolean expressions
LabelRoute::wherePathMatchesText('(europe | asia) & !africa')->get();

// Prefix matching (labels starting with "rus")
LabelRoute::wherePathMatchesText('rus*')->get();

// Case-insensitive matching
LabelRoute::wherePathMatchesText('europe@')->get();
```

### ltxtquery Syntax

| Syntax | Meaning |
|--------|---------|
| `label` | Path contains "label" |
| `a & b` | Path contains both a AND b |
| `a \| b` | Path contains a OR b |
| `!a` | Path does NOT contain a |
| `(...)` | Group expressions |
| `label*` | Prefix match (labels starting with "label") |
| `label@` | Case-insensitive match |

## Ancestry Queries

### Descendants

Find all routes that descend from a path:

```php
// All routes under "priority" (not including "priority" itself)
LabelRoute::whereDescendantOf('priority')->get();
// Matches: priority.high, priority.high.critical, priority.low
```

### Ancestors

Find all routes that are ancestors of a path:

```php
// All ancestors of "priority.high.critical"
LabelRoute::whereAncestorOf('priority.high.critical')->get();
// Matches: priority, priority.high
```

## Depth Queries

Routes have a `depth` property (0 = root):

```php
// Root labels only
LabelRoute::whereDepth(0)->get();

// Second level
LabelRoute::whereDepth(1)->get();

// Depth range
LabelRoute::whereDepthBetween(1, 3)->get();

// Max depth
LabelRoute::whereDepthLte(2)->get();

// Min depth
LabelRoute::whereDepthGte(1)->get();
```

## Combining Scopes

```php
// Pattern + depth
LabelRoute::wherePathMatches('priority.*')
    ->whereDepthLte(2)
    ->get();

// Multiple patterns (OR)
LabelRoute::where(function ($query) {
    $query->wherePathMatches('priority.*')
          ->orWhere->wherePathMatches('type.*');
})->get();
```

## Database Adapters

Query scopes use database-specific adapters for optimal performance:

| Database | Pattern Matching | Notes |
|----------|-----------------|-------|
| PostgreSQL | Native lquery/ltxtquery (with ltree) or regex | Best performance with ltree extension |
| MySQL | `REGEXP` function | Good performance |
| SQLite | PHP callback with custom functions | Works for testing |

The adapter is automatically selected based on your connection.

## Ltree Function Scopes

These scopes expose PostgreSQL ltree functions across all databases:

### Select Helpers

```php
// Add path depth to results
LabelRoute::selectNlevel('level')->get();
// Returns: [['path' => 'a.b.c', 'level' => 3], ...]

// Extract subpath segment(s)
LabelRoute::selectSubpath(1, 2, 'middle')->get();
// For 'a.b.c.d' returns: [['path' => 'a.b.c.d', 'middle' => 'b.c'], ...]

// Concatenate path columns
LabelRoute::selectConcat('path', "'suffix'", 'extended')->get();
```

### Filter Helpers

```php
// Filter by nlevel (1-indexed, unlike depth which is 0-indexed)
LabelRoute::whereNlevel(3)->get();  // Paths with exactly 3 labels

// Filter by subpath value
LabelRoute::whereSubpathEquals(0, 1, 'priority')->get();  // First segment is "priority"
```

### Array Operators (PostgreSQL with ltree only)

```php
// Check support first
if (LabelRoute::supportsArrayOperators()) {
    // Find paths that have an ancestor in the array
    LabelRoute::wherePathInAncestors(['a.b', 'x.y'])->get();

    // Find paths that have a descendant in the array
    LabelRoute::wherePathInDescendants(['a.b.c', 'x.y.z'])->get();
}

// Static helpers for single-path operations
$ancestor = LabelRoute::firstAncestorFrom('a.b.c.d', ['a', 'a.b', 'x.y']);
// Returns: 'a.b'

$descendant = LabelRoute::firstDescendantFrom('a', ['a.b.c', 'x.y.z']);
// Returns: 'a.b.c'
```

## Ltree Static Helpers

The `Ltree` class provides PHP implementations of ltree functions for use outside queries:

```php
use Birdcar\LabelGraph\Ltree\Ltree;

// Count path segments
Ltree::nlevel('a.b.c');  // 3

// Extract subpath
Ltree::subpath('a.b.c.d', 1, 2);  // 'b.c'
Ltree::subpath('a.b.c.d', -2);    // 'c.d' (negative = from end)

// Extract by position range
Ltree::subltree('a.b.c.d', 1, 3);  // 'b.c'

// Find subpath position
Ltree::index('a.b.c.d', 'b.c');  // 1

// Longest common ancestor
Ltree::lca(['a.b.c', 'a.b.d', 'a.b.e']);  // 'a.b'

// Validate and convert text to ltree format
Ltree::text2ltree('a.b.c');  // 'a.b.c' (throws on invalid)

// Concatenate paths
Ltree::concat('a.b', 'c.d');  // 'a.b.c.d'
```

## Instance Methods

LabelRoute instances also have navigation methods:

```php
$route = LabelRoute::where('path', 'priority.high')->first();

// Collections
$route->ancestors();     // Collection<LabelRoute>
$route->descendants();   // Collection<LabelRoute>
$route->children();      // Collection<LabelRoute> (direct only)

// Single route
$route->parent();        // LabelRoute|null

// Booleans
$route->isRoot();
$route->isLeaf();
$route->isAncestorOf($other);
$route->isDescendantOf($other);
```
