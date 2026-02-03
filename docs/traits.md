<!-- Keywords: HasLabels trait, attachRoute, syncRoutes, polymorphic tagging Laravel -->

# HasLabels Trait

> Add hierarchical labeling to any Eloquent model with attachRoute(), syncRoutes(), and query scopes.

The `HasLabels` trait adds labeling capabilities to any Eloquent model.

## Setup

```php
use Birdcar\LabelGraph\Models\Concerns\HasLabels;

class Ticket extends Model
{
    use HasLabels;
}
```

## Attaching Routes

```php
// Attach by path string
$ticket->attachRoute('priority.high');
$ticket->attachRoute('type.bug');

// Attach by LabelRoute model
$route = LabelRoute::where('path', 'priority.high')->first();
$ticket->attachRoute($route);
```

## Detaching Routes

```php
// Detach by path string
$ticket->detachRoute('priority.high');

// Detach by model
$ticket->detachRoute($route);
```

## Syncing Routes

Replace all attached routes:

```php
$ticket->syncRoutes([
    'priority.critical',
    'type.bug',
    'area.api',
]);
```

## Checking Routes

```php
// Check exact route
$ticket->hasRoute('priority.high');  // bool

// Check by pattern
$ticket->hasRouteMatching('priority.*');  // bool
```

## Reading Routes

```php
// Get all attached routes (eager-loadable relationship)
$ticket->labelRoutes;  // Collection<LabelRoute>

// Get paths as array
$ticket->label_paths;  // ['priority.high', 'type.bug']
```

## Query Scopes

### Exact Match

```php
// Models with exact route attached
Ticket::whereHasRoute('priority.high')->get();
```

### Pattern Match

```php
// Models with routes matching pattern
Ticket::whereHasRouteMatching('priority.*')->get();
Ticket::whereHasRouteMatching('*.bug')->get();
```

### Descendants

```php
// Models with routes descending from path
Ticket::whereHasRouteDescendantOf('priority')->get();
// Matches: priority.high, priority.high.critical, etc.
```

### Ancestors

```php
// Models with routes that are ancestors of path
Ticket::whereHasRouteAncestorOf('priority.high.critical')->get();
// Matches: priority, priority.high
```

### Eager Loading

```php
// Load routes with count
Ticket::withRoutesCount()->get();

// Eager load routes
Ticket::withRoutes()->get();
```

## Combining Scopes

```php
// Complex queries
Ticket::query()
    ->whereHasRouteMatching('priority.*')
    ->whereHasRoute('status.open')
    ->withRoutes()
    ->get();

// With other conditions
Ticket::query()
    ->where('assigned_to', $userId)
    ->whereHasRouteDescendantOf('area.frontend')
    ->get();
```

## Error Handling

```php
use Birdcar\LabelGraph\Exceptions\InvalidRouteException;

try {
    $ticket->attachRoute('nonexistent.path');
} catch (InvalidRouteException $e) {
    // Route not found
}
```
