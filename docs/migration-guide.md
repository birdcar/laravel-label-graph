# Migration Guide

## Upgrading Between Versions

### 0.x to 1.0

> This section will be updated when 1.0 is released.

Currently in pre-1.0 development. API may change between minor versions.

## Migrating From Other Solutions

### From Nested Sets (baum, kalnoy/nestedset)

Nested sets use `lft`/`rgt` columns for tree traversal. Label Tree uses relationships and materialized paths.

**Key differences**:
- Nested sets: Single parent only
- Label Tree: Multiple parents allowed

**Migration approach**:

```php
// 1. Create labels from your existing nodes
$categories = Category::all();
foreach ($categories as $category) {
    Label::create([
        'name' => $category->name,
        'slug' => $category->slug,
    ]);
}

// 2. Create relationships from parent_id
foreach ($categories->whereNotNull('parent_id') as $category) {
    $parent = Label::where('slug', $category->parent->slug)->first();
    $child = Label::where('slug', $category->slug)->first();

    LabelRelationship::create([
        'parent_label_id' => $parent->id,
        'child_label_id' => $child->id,
    ]);
}

// 3. Routes are auto-generated

// 4. Migrate attachments (if applicable)
// Your migration will depend on how you attached categories to models
```

### From Tags (spatie/laravel-tags)

Tags are typically flat; Label Tree adds hierarchy.

**Migration approach**:

```php
// 1. Create labels from tags
$tags = Tag::all();
foreach ($tags as $tag) {
    Label::create([
        'name' => $tag->name,
        'slug' => $tag->slug,
    ]);
}

// 2. Add hierarchy relationships manually
// (tags don't have hierarchy, so you'll design this)

// 3. Migrate taggable attachments
// spatie/laravel-tags uses a 'taggables' pivot table
// Label Tree uses 'labelables'
```

### From Custom Adjacency Lists

If you have a `parent_id` column on a categories/tags table:

```php
// 1. Create labels
$items = YourModel::all();
foreach ($items as $item) {
    Label::create([
        'name' => $item->name,
        'slug' => Str::slug($item->name),
    ]);
}

// 2. Create relationships
foreach ($items->whereNotNull('parent_id') as $item) {
    $parentLabel = Label::where('slug', Str::slug($item->parent->name))->first();
    $childLabel = Label::where('slug', Str::slug($item->name))->first();

    LabelRelationship::create([
        'parent_label_id' => $parentLabel->id,
        'child_label_id' => $childLabel->id,
    ]);
}
```

## Database Migration Tips

### Keeping Old Tables During Transition

```php
// In your migration
Schema::table('old_categories', function (Blueprint $table) {
    $table->string('label_graph_route')->nullable();
});
```

Map old records to new routes for gradual migration.

### Batch Route Attachment

```php
// Efficiently attach routes to many models
$models = Model::whereNotNull('old_category_id')->get();

foreach ($models as $model) {
    $route = LabelRoute::where('path', $mapping[$model->old_category_id])->first();
    if ($route) {
        $model->attachRoute($route);
    }
}
```

### Validating Migration

After migration, run validation:

```bash
php artisan label-graph:validate
php artisan label-graph:visualize
```

Check that:
- All expected routes exist
- No orphaned routes
- Attachment counts match expectations
