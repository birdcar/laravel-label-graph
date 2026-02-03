# When to Use laravel-label-graph

## Quick Answer

Use laravel-label-graph when you need hierarchical labels where items can belong to multiple categories simultaneously (DAG), and you want fast pattern-based queries like "find all `priority.*` labels". For flat tags, use spatie/laravel-tags. For strict single-parent trees, use nested sets.

**Use laravel-label-graph when:**
- Products appear in multiple categories (Electronics AND Gaming)
- You need lquery patterns (`type.bug.*`, `*.critical`)
- Fast ancestor/descendant queries matter
- Labels form a graph, not just a tree

**Use alternatives when:**
- Tags are flat with no hierarchy → spatie/laravel-tags
- Each item has exactly one parent → kalnoy/nestedset
- You prefer recursive CTEs → staudenmeir/laravel-adjacency-list

## Decision Tree

```
Need to categorize/tag items?
├── No hierarchy needed (flat tags)?
│   └── Yes → spatie/laravel-tags
│   └── No → Continue...
│
├── Can items have MULTIPLE parents?
│   └── No (strict tree) → kalnoy/nestedset
│   └── Yes (DAG) → Continue...
│
├── Need pattern matching (lquery)?
│   └── Yes → laravel-label-graph
│   └── No → Continue...
│
└── Preference?
    ├── Pre-computed paths (fast reads) → laravel-label-graph
    └── Recursive CTEs (flexible) → staudenmeir/laravel-adjacency-list
```

## Use Case Examples

### Perfect Fit: E-commerce Product Categories

A wireless gaming mouse should appear in both "Electronics > Computer Accessories > Mice" AND "Gaming > PC Gaming > Accessories".

```php
// Create the category structure
$electronics = Label::create(['name' => 'Electronics']);
$gaming = Label::create(['name' => 'Gaming']);
$mice = Label::create(['name' => 'Mice']);
$accessories = Label::create(['name' => 'Accessories']);

// Multi-parent relationships
$mice->addParent($electronics);     // electronics.mice
$accessories->addParent($gaming);   // gaming.accessories
$mice->addParent($accessories);     // gaming.accessories.mice

// Product belongs to multiple category paths
$product->attachRoute($mice->routes->first());  // electronics.mice
$product->attachRoute($mice->routes->last());   // gaming.accessories.mice

// Query: Find all products in gaming.* categories
Product::whereLabelRouteMatches('gaming.*')->get();
```

**Why laravel-label-graph?** Multi-parent hierarchy is the core requirement. Pattern matching makes browsing/filtering intuitive.

### Perfect Fit: Issue Tracker Labels

GitHub-style labels like `priority.high`, `type.bug.crash`, `status.in-progress`.

```php
// Hierarchical labels
$priority = Label::create(['name' => 'Priority']);
$high = Label::create(['name' => 'High']);
$critical = Label::create(['name' => 'Critical']);

$high->addParent($priority);      // priority.high
$critical->addParent($high);      // priority.high.critical

// Attach to issues
$issue->attachRoute($critical->primaryRoute());

// Query patterns
Issue::whereLabelRouteMatches('priority.*')->get();           // All priority issues
Issue::whereLabelRouteMatches('priority.high.*')->get();      // High priority and above
Issue::whereLabelRouteMatches('*.critical')->get();           // Critical anything
```

**Why laravel-label-graph?** Pattern matching is essential for filtering. Hierarchical structure enables cascade queries.

### Perfect Fit: Content Taxonomy

Documentation that spans multiple topics: "Laravel + Testing + PHPUnit" where each topic has sub-topics.

```php
// Topics with subtopics
$laravel = Label::create(['name' => 'Laravel']);
$testing = Label::create(['name' => 'Testing']);
$phpunit = Label::create(['name' => 'PHPUnit']);

$phpunit->addParent($testing);    // testing.phpunit
$testing->addParent($laravel);    // laravel.testing (Laravel's testing features)

// Article about Laravel testing with PHPUnit
$article->attachRoute($phpunit->routes->first());  // testing.phpunit
$article->attachRoute(
    Route::where('path', 'laravel.testing')->first()
);

// Find all testing content
Article::whereLabelRouteMatches('*.testing.*')->get();
```

**Why laravel-label-graph?** Content often spans multiple taxonomies. Pattern matching enables faceted search.

### Not Ideal: Simple Blog Tags

Blog posts with tags like "php", "tutorial", "beginner" - no hierarchy, no multi-parent.

```php
// DON'T use laravel-label-graph for this
// It's overkill - you don't need hierarchy or pattern matching

// DO use spatie/laravel-tags instead
$post->attachTags(['php', 'tutorial', 'beginner']);
$post->tags; // Simple collection
Post::withAnyTags(['php'])->get();
```

**Why NOT laravel-label-graph?** Flat tags don't need materialized paths, route tables, or lquery patterns. spatie/laravel-tags is simpler and more appropriate.

### Not Ideal: Permission System

Role-based access control with permissions like "posts.create", "users.delete".

```php
// DON'T use laravel-label-graph for this
// Permissions need specialized middleware, guards, and policies

// DO use spatie/laravel-permission instead
$user->givePermissionTo('posts.create');
$user->hasPermissionTo('posts.create'); // Built-in checks
@can('posts.create') // Blade directives
```

**Why NOT laravel-label-graph?** Permission systems need authorization middleware, gate integration, and role inheritance logic that spatie/laravel-permission provides out of the box.

### Not Ideal: Nested Comments

Threaded discussions where replies are children of parent comments.

```php
// DON'T use laravel-label-graph for this
// Comments are strict trees (one parent) and need different queries

// DO use kalnoy/nestedset or staudenmeir/laravel-adjacency-list
$comment->children;           // Direct replies
$comment->descendants;        // All nested replies
$comment->ancestors;          // Thread path to root
```

**Why NOT laravel-label-graph?** Comment threads are strict trees (single parent). You don't need multi-parent or lquery patterns. Nested sets or adjacency lists are better fits.

## Anti-Patterns

### Storing user-facing content in labels

Labels are for categorization, not content storage. Don't put long descriptions or HTML in label fields.

```php
// Bad: Label as content
Label::create([
    'name' => 'Article about Laravel',
    'description' => '<p>Long article content...</p>',  // Wrong!
]);

// Good: Label as categorization
Label::create(['name' => 'Laravel Tutorials']);
$article->attachRoute($label->primaryRoute());  // Article model has content
```

### Creating labels for unique items

If every item gets its own unique label, you're not categorizing - you're duplicating data.

```php
// Bad: Label per product
$label = Label::create(['name' => $product->name]);
$product->attachRoute($label->primaryRoute());

// Good: Labels for shared categories
$category = Label::firstOrCreate(['name' => 'Electronics']);
$product->attachRoute($category->primaryRoute());
```

### Deep nesting without pattern matching

If you have deeply nested hierarchies but never query by pattern, adjacency lists may be simpler.

```php
// If you never do this:
Product::whereLabelRouteMatches('electronics.*.accessories')->get();

// And only do this:
$label->ancestors;
$label->descendants;

// Consider staudenmeir/laravel-adjacency-list instead - no route table needed
```

### Using labels as the sole data model

Labels augment your models; they don't replace proper domain modeling.

```php
// Bad: Everything is a label
$userLabel = Label::create(['name' => 'John Doe', 'type' => 'user']);
$orderLabel = Label::create(['name' => 'Order #123', 'type' => 'order']);
$userLabel->addChild($orderLabel);  // "User has order"

// Good: Proper models with label categorization
$user = User::create(['name' => 'John Doe']);
$order = Order::create(['user_id' => $user->id]);
$order->attachRoute($priorityLabel->primaryRoute());  // Categorize the order
```

## Summary

| Requirement | Recommended Package |
|-------------|---------------------|
| Flat tags, no hierarchy | spatie/laravel-tags |
| Single-parent tree, read-heavy | kalnoy/nestedset |
| Multi-parent, no patterns needed | staudenmeir/laravel-adjacency-list |
| Multi-parent WITH pattern matching | **laravel-label-graph** |
| Permissions/roles | spatie/laravel-permission |
