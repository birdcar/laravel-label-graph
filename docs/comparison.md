# Package Comparison

## Quick Answer

**laravel-label-graph** is the only Laravel package that combines multi-parent hierarchies (DAG), lquery pattern matching, and materialized paths. Use spatie/laravel-tags for flat tags without hierarchy. Use kalnoy/nestedset for single-parent trees with fast reads. Use staudenmeir/laravel-adjacency-list for multi-parent graphs without pattern matching.

## Comparison Matrix

| Feature | laravel-label-graph | spatie/laravel-tags | kalnoy/nestedset | staudenmeir/laravel-adjacency-list |
|---------|-------------------|--------------------|-----------------|------------------------------------|
| **Multi-parent** | Yes (DAG) | No | No | Yes (graph mode) |
| **Hierarchy** | Yes | No | Yes (tree) | Yes (tree or graph) |
| **Pattern matching** | Yes (lquery) | No | No | No |
| **Query method** | Materialized paths | Direct lookup | Nested sets | Recursive CTEs |
| **Read performance** | Fast (pre-computed) | Fast | Very fast | Moderate (recursive) |
| **Write performance** | Moderate | Fast | Slow (rebalancing) | Fast |
| **PostgreSQL ltree** | Optional native | N/A | No | No |
| **Polymorphic** | Yes | Yes | No | No |
| **Laravel 11+** | Yes | Yes | Yes | Yes |
| **Actively maintained** | Yes | Yes (Oct 2025) | Yes (May 2025) | Yes (Jul 2025) |

## Detailed Comparison

### vs spatie/laravel-tags

**spatie/laravel-tags** is the most popular Laravel tagging package with 1.7k+ stars. It provides flat, non-hierarchical tags with excellent features like translations, tag types, and sortable tags.

**Choose spatie/laravel-tags when:**
- Tags are flat (no parent-child relationships)
- You need tag translations
- You want the most battle-tested solution for simple tagging

**Choose laravel-label-graph when:**
- Tags need hierarchy (e.g., `priority.high.critical`)
- Items belong to multiple categories simultaneously
- You need pattern matching queries

**Key differences:**
- spatie/laravel-tags: `$post->tags` returns flat tag collection
- laravel-label-graph: `$post->routes` returns hierarchical paths with ancestor queries

### vs kalnoy/nestedset

**kalnoy/nestedset** (also known as lazychaser/laravel-nestedset) implements the classic nested set model. It excels at read-heavy single-parent trees like menus and category hierarchies.

**Choose kalnoy/nestedset when:**
- Each item has exactly one parent (strict tree)
- Reads vastly outnumber writes
- You need extremely fast ancestor/descendant queries
- Building menus, org charts, or single-category systems

**Choose laravel-label-graph when:**
- Items can have multiple parents
- Write performance matters (nested sets require rebalancing)
- You need pattern matching on paths

**Key differences:**
- kalnoy/nestedset: `$node->ancestors()` via left/right values (very fast)
- laravel-label-graph: Multiple paths per label, lquery pattern matching

**Migration complexity:** Moderate. Nested sets use `_lft`/`_rgt` columns; laravel-label-graph uses separate relationship and route tables. Requires data transformation.

### vs staudenmeir/laravel-adjacency-list

**staudenmeir/laravel-adjacency-list** uses recursive Common Table Expressions (CTEs) for hierarchical queries. It's the most flexible package, supporting both trees and graphs (multi-parent).

**Choose staudenmeir/laravel-adjacency-list when:**
- You prefer recursive CTEs over materialized paths
- Your database supports CTEs well (MySQL 8+, PostgreSQL 9.4+)
- You want one package for both tree and graph structures
- Pattern matching isn't needed

**Choose laravel-label-graph when:**
- You need lquery pattern matching (`priority.*`, `*.bug`)
- You want pre-computed paths for consistent query performance
- You're using PostgreSQL and want native ltree support

**Key differences:**
- staudenmeir/laravel-adjacency-list: Real-time recursive queries
- laravel-label-graph: Pre-computed materialized paths with pattern language

**Migration complexity:** Low-moderate. Both support multi-parent; main change is adopting materialized paths and lquery patterns.

## When NOT to Use laravel-label-graph

**Overkill scenarios:**
- Simple flat tags → Use spatie/laravel-tags
- Strict single-parent trees with rare writes → Use kalnoy/nestedset
- Already using CTEs successfully → staudenmeir/laravel-adjacency-list works fine

**Wrong tool scenarios:**
- Permission/role systems → Use spatie/laravel-permission
- Nested comments/threading → Use adjacency list or nested sets
- File system trees → Built-in PHP or dedicated package

**Technical constraints:**
- Legacy databases without modern SQL support
- Extremely write-heavy workloads (consider adjacency lists)

## Migration Complexity

### From spatie/laravel-tags

**Complexity:** Low-Moderate

1. Tags become Labels (similar structure)
2. No hierarchy to preserve (fresh start)
3. Polymorphic relationships are similar

```php
// Migration approach
$tags = Tag::all();
foreach ($tags as $tag) {
    Label::create(['name' => $tag->name, 'slug' => $tag->slug]);
}
// Then migrate model attachments
```

### From kalnoy/nestedset

**Complexity:** Moderate

1. Flatten nested set to adjacency list mentally
2. Each nested set node becomes a Label
3. Parent-child becomes LabelRelationship
4. Routes auto-generate from relationships

```php
// Migration requires traversing the tree
$nodes = Category::get()->toTree();
// Recursively create Labels and relationships
```

### From staudenmeir/laravel-adjacency-list

**Complexity:** Low

1. Similar multi-parent model
2. Replace recursive queries with route-based queries
3. Add lquery patterns where beneficial

```php
// Direct mapping possible
// parent_id relationships → LabelRelationship entries
```

## Package Summary

| Package | Best For | Stars |
|---------|----------|-------|
| **laravel-label-graph** | Multi-parent hierarchies with pattern matching | — |
| **spatie/laravel-tags** | Simple flat tagging | 1.7k+ |
| **kalnoy/nestedset** | Read-heavy single-parent trees | 3.5k+ |
| **staudenmeir/laravel-adjacency-list** | Flexible tree/graph with CTEs | 1.5k+ |
