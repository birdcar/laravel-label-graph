<!-- Keywords: install laravel-label-graph, setup hierarchical labels, DAG labels Laravel -->

# Installation

> Install and configure laravel-label-graph for hierarchical labeling with multi-parent support.

## Requirements

- PHP 8.3 or higher
- Laravel 11.0 or higher
- One of: SQLite, PostgreSQL 14+, MySQL 8+

## Install via Composer

```bash
composer require birdcar/laravel-label-graph
```

## Publish Migrations

```bash
php artisan vendor:publish --tag=label-graph-migrations
```

This publishes four migrations:
- `create_labels_table` - The main labels table
- `create_label_relationships_table` - Parent-child relationships
- `create_label_routes_table` - Materialized paths
- `create_labelables_table` - Polymorphic pivot for attaching routes to models

## Run Migrations

```bash
php artisan migrate
```

## Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=label-graph-config
```

This creates `config/label-graph.php` where you can customize table names.

## Verify Installation

```bash
php artisan tinker
>>> use Birdcar\LabelGraph\Models\Label;
>>> Label::create(['name' => 'Test']);
```

If you see a Label model returned, installation was successful!

## Alternative: Install Command

You can also use the install command which publishes and runs migrations:

```bash
php artisan label-graph:install
```

## PostgreSQL ltree Extension (Recommended)

If you're using PostgreSQL, install the `ltree` extension for significantly improved query performance:

```bash
php artisan label-graph:install-ltree
```

Benefits of ltree:
- Native lquery pattern matching with GiST index support
- Native ltxtquery boolean text search
- Array operators for efficient batch queries
- Optimized ancestor/descendant queries

The package works without ltree, but PostgreSQL users will see major performance improvements for pattern queries on large datasets.

### Manual Installation

If the command fails due to permissions, install manually via psql:

```bash
psql -d your_database -c "CREATE EXTENSION IF NOT EXISTS ltree;"
```

For managed databases (AWS RDS, Azure, etc.), enable the extension through the provider's dashboard.

## Optimized Indexes

For best performance on pattern queries, add a GiST index to the routes table. In a migration:

```php
use Birdcar\LabelGraph\Schema\LtreeIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('label_routes', function (Blueprint $table) {
    // Creates optimized index based on your database
    LtreeIndex::create($table, 'path');

    // For PostgreSQL with ltree, create a GiST index
    // LtreeIndex::createGist($table, 'path');
});
```
