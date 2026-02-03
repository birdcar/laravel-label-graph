# Query Cookbook

Comprehensive query pattern reference for laravel-label-graph. Each example includes use case, code, and performance notes.

---

## LabelRoute Queries

### wherePathMatches (lquery patterns)

Match routes using PostgreSQL lquery-style patterns.

**Pattern syntax:**
- `*` - matches zero or more labels
- `{n}` - matches exactly n labels
- `{n,m}` - matches between n and m labels
- `a|b` - matches either a or b

#### Example 1: Wildcard suffix

**Use case:** Find all routes under a category.

```php
<?php

use Birdcar\LabelGraph\Models\LabelRoute;

// Find all priority-related routes
$routes = LabelRoute::wherePathMatches('priority.*')->get();
// Matches: priority, priority.high, priority.high.critical

// Find all routes ending in 'bug'
$bugRoutes = LabelRoute::wherePathMatches('*.bug')->get();
// Matches: type.bug, area.type.bug
```

**Performance:** Fast. Uses index on path column with LIKE prefix matching.

#### Example 2: Wildcard prefix

**Use case:** Find routes by their leaf label regardless of ancestry.

```php
<?php

// Find all 'critical' routes anywhere in hierarchy
$criticalRoutes = LabelRoute::wherePathMatches('*.critical')->get();
// Matches: priority.critical, priority.high.critical, status.critical
```

**Performance:** Moderate. Requires full scan when pattern starts with wildcard.

#### Example 3: Middle wildcard

**Use case:** Find routes with specific start and end.

```php
<?php

// Find routes starting with 'electronics' and ending with 'wireless'
$routes = LabelRoute::wherePathMatches('electronics.*.wireless')->get();
// Matches: electronics.mice.wireless, electronics.keyboards.wireless
```

**Performance:** Moderate. Combines prefix and suffix matching.

#### Example 4: Alternatives

**Use case:** Match multiple specific labels.

```php
<?php

// Find bug OR feature routes
$routes = LabelRoute::wherePathMatches('type.bug|feature')->get();
// Matches: type.bug, type.feature

// Find P0 OR P1 priority
$urgent = LabelRoute::wherePathMatches('priority.p0|p1')->get();
// Matches: priority.p0, priority.p1
```

**Performance:** Fast. Translates to OR conditions.

#### Example 5: Quantifiers

**Use case:** Match routes at specific depths.

```php
<?php

// Find routes exactly 2 levels deep
$routes = LabelRoute::wherePathMatches('*.{2}')->get();
// Matches: priority.high, type.bug, area.frontend

// Find routes 2-3 levels deep
$routes = LabelRoute::wherePathMatches('*.{2,3}')->get();
// Matches: priority.high, priority.high.critical
```

**Performance:** Requires depth calculation. Use `whereDepth()` for better performance.

---

### whereDescendantOf / whereAncestorOf

Query routes by hierarchical position.

#### Example 6: Find all descendants

**Use case:** Get all routes under a category including nested.

```php
<?php

// All routes under 'priority' (not including priority itself)
$descendants = LabelRoute::whereDescendantOf('priority')->get();
// Matches: priority.high, priority.high.critical, priority.low

// Include the parent route itself
$withParent = LabelRoute::whereDescendantOf('priority', includeSelf: true)->get();
// Matches: priority, priority.high, priority.high.critical
```

**Performance:** Fast. Uses path prefix matching with index.

#### Example 7: Find all ancestors

**Use case:** Get the path from root to a specific route.

```php
<?php

// All ancestors of 'priority.high.critical'
$ancestors = LabelRoute::whereAncestorOf('priority.high.critical')->get();
// Matches: priority, priority.high

// Include the route itself (breadcrumb trail)
$breadcrumbs = LabelRoute::whereAncestorOf('priority.high.critical', includeSelf: true)->get();
// Matches: priority, priority.high, priority.high.critical
```

**Performance:** Fast. Iterates through path segments.

---

### whereDepth / whereDepthBetween

Filter routes by hierarchy depth.

#### Example 8: Root routes only

**Use case:** Get top-level categories.

```php
<?php

// Depth 0 = root routes (no parent)
$roots = LabelRoute::whereDepth(0)->get();
// Matches: priority, type, area, electronics, gaming

// First-level children
$firstLevel = LabelRoute::whereDepth(1)->get();
// Matches: priority.high, type.bug, electronics.mice
```

**Performance:** Very fast. Direct column comparison with index.

#### Example 9: Depth range

**Use case:** Get routes within a depth range.

```php
<?php

// Routes 1-2 levels deep (children and grandchildren of roots)
$routes = LabelRoute::whereDepthBetween(1, 2)->get();
// Matches: priority.high, priority.high.critical, type.bug
```

**Performance:** Fast. Range query on indexed column.

---

## HasLabels Model Queries

Queries for models using the `HasLabels` trait.

### whereHasRoute

Exact match on attached route.

#### Example 10: Exact route match

**Use case:** Find models with a specific label.

```php
<?php

use App\Models\Ticket;

// Find tickets labeled exactly 'priority.high.critical'
$criticalTickets = Ticket::whereHasRoute('priority.high.critical')->get();

// Combine with other query conditions
$openCritical = Ticket::query()
    ->whereHasRoute('priority.high.critical')
    ->where('status', 'open')
    ->get();
```

**Performance:** Fast. Uses polymorphic join with exact match.

---

### whereHasRouteMatching

Pattern match on attached routes.

#### Example 11: Find by pattern

**Use case:** Find models matching a label pattern.

```php
<?php

// All tickets with any priority label
$priorityTickets = Ticket::whereHasRouteMatching('priority.*')->get();

// All tickets in any 'bug' category
$bugTickets = Ticket::whereHasRouteMatching('*.bug')->get();

// Tickets with high or critical priority
$urgent = Ticket::whereHasRouteMatching('priority.high|critical')->get();
```

**Performance:** Depends on pattern. Prefix patterns are fast; suffix patterns require more work.

#### Example 12: Multiple patterns

**Use case:** Find models matching any of several patterns.

```php
<?php

// Method 1: Multiple calls (OR logic)
$tickets = Ticket::where(function ($query) {
    $query->whereHasRouteMatching('priority.high.*')
          ->orWhereHasRouteMatching('status.urgent');
})->get();

// Method 2: Alternative pattern
$tickets = Ticket::whereHasRouteMatching('priority.high.*|status.urgent')->get();
```

---

### whereHasRouteDescendantOf / whereHasRouteAncestorOf

Ancestry queries on attached routes.

#### Example 13: Descendants on models

**Use case:** Find models with labels under a category.

```php
<?php

// All tickets under 'priority' category (any priority level)
$tickets = Ticket::whereHasRouteDescendantOf('priority')->get();
// Matches tickets with: priority.high, priority.low, priority.high.critical

// Include exact match
$tickets = Ticket::whereHasRouteDescendantOf('priority', includeSelf: true)->get();
// Also matches tickets with exactly: priority
```

**Performance:** Fast. Uses path prefix in subquery.

#### Example 14: Ancestors on models

**Use case:** Find models whose labels are ancestors of a path.

```php
<?php

// Find tickets that are "above" a specific route
$tickets = Ticket::whereHasRouteAncestorOf('priority.high.critical')->get();
// Matches tickets with: priority, priority.high
```

**Performance:** Moderate. Requires ancestor path calculation.

---

## Advanced Patterns

### Combining Scopes

#### Example 15: Multiple label conditions

**Use case:** Complex filtering by multiple labels.

```php
<?php

// Critical bugs in frontend area
$tickets = Ticket::query()
    ->whereHasRouteMatching('priority.high.*')  // Any high priority
    ->whereHasRoute('type.bug')                  // Exact bug type
    ->whereHasRouteMatching('area.frontend.*')  // Any frontend area
    ->get();
```

#### Example 16: Label + model conditions

**Use case:** Filter by labels AND model attributes.

```php
<?php

// Open critical tickets assigned to specific user
$tickets = Ticket::query()
    ->whereHasRoute('priority.high.critical')
    ->where('status', 'open')
    ->where('assigned_to', $userId)
    ->orderBy('created_at', 'desc')
    ->get();
```

---

### Subqueries

#### Example 17: Models with specific label count

**Use case:** Find models with exactly N labels.

```php
<?php

// Tickets with exactly 3 labels
$tickets = Ticket::withRoutesCount()
    ->having('label_routes_count', '=', 3)
    ->get();

// Tickets with more than 5 labels (over-categorized?)
$overLabeled = Ticket::withRoutesCount()
    ->having('label_routes_count', '>', 5)
    ->get();
```

#### Example 18: Models without any labels

**Use case:** Find uncategorized items.

```php
<?php

// Tickets missing labels
$unlabeled = Ticket::whereDoesntHave('labelRoutes')->get();

// Alternative with count
$unlabeled = Ticket::withRoutesCount()
    ->having('label_routes_count', '=', 0)
    ->get();
```

---

### Performance Optimization

#### Example 19: Eager loading

**Use case:** Avoid N+1 queries when displaying labels.

```php
<?php

// Bad: N+1 queries
$tickets = Ticket::all();
foreach ($tickets as $ticket) {
    echo $ticket->label_paths; // Queries for each ticket
}

// Good: Eager load
$tickets = Ticket::withRoutes()->get();
foreach ($tickets as $ticket) {
    echo $ticket->label_paths; // No additional queries
}
```

#### Example 20: Selective loading

**Use case:** Load only specific route data.

```php
<?php

// Load routes with their labels (for display)
$tickets = Ticket::with(['labelRoutes.label'])->get();
foreach ($tickets as $ticket) {
    foreach ($ticket->labelRoutes as $route) {
        echo $route->label->name; // Label model available
    }
}
```

---

## Query Summary Table

| Query | Use Case | Performance |
|-------|----------|-------------|
| `whereHasRoute('exact')` | Exact label match | Fast |
| `whereHasRouteMatching('prefix.*')` | Pattern with prefix | Fast |
| `whereHasRouteMatching('*.suffix')` | Pattern with suffix | Moderate |
| `whereHasRouteDescendantOf('path')` | All under category | Fast |
| `whereHasRouteAncestorOf('path')` | Ancestors of path | Moderate |
| `LabelRoute::whereDepth(n)` | Specific depth | Very Fast |
| `LabelRoute::wherePathMatches()` | lquery patterns | Varies |

---

*Use these patterns as building blocks. Combine them for complex queries.*
