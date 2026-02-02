# Laravel Label Tree

[![CI](https://github.com/birdcar/laravel-label-tree/actions/workflows/ci.yaml/badge.svg)](https://github.com/birdcar/laravel-label-tree/actions/workflows/ci.yaml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/birdcar/laravel-label-tree.svg)](https://packagist.org/packages/birdcar/laravel-label-tree)
[![Total Downloads](https://img.shields.io/packagist/dt/birdcar/laravel-label-tree.svg)](https://packagist.org/packages/birdcar/laravel-label-tree)
[![License](https://img.shields.io/packagist/l/birdcar/laravel-label-tree.svg)](https://packagist.org/packages/birdcar/laravel-label-tree)

A Laravel package for hierarchical labels stored as a directed acyclic graph (DAG) with materialized path routes.

## Installation

```bash
composer require birdcar/laravel-label-tree
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag=label-tree-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=label-tree-config
```

## Quick Start

### Create Labels

```php
use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;

// Create root labels
$priority = Label::create(['name' => 'Priority']);
$high = Label::create(['name' => 'High']);
$critical = Label::create(['name' => 'Critical']);

// Create hierarchy
LabelRelationship::create([
    'parent_label_id' => $priority->id,
    'child_label_id' => $high->id,
]);

LabelRelationship::create([
    'parent_label_id' => $high->id,
    'child_label_id' => $critical->id,
]);

// Routes are automatically generated:
// priority
// priority.high
// priority.high.critical
```

### Attach Labels to Models

```php
use Birdcar\LabelTree\Models\Concerns\HasLabels;

class Ticket extends Model
{
    use HasLabels;
}

// Attach routes by path
$ticket->attachRoute('priority.high.critical');
$ticket->attachRoute('type.bug');

// Query by exact route
Ticket::whereHasRoute('priority.high')->get();

// Query by pattern (lquery-style: * matches zero or more labels)
Ticket::whereHasRouteMatching('priority.*')->get();  // priority and descendants
Ticket::whereHasRouteMatching('*.bug')->get();       // any path ending in bug

// Query descendants/ancestors
Ticket::whereHasRouteDescendantOf('priority')->get();
Ticket::whereHasRouteAncestorOf('priority.high.critical')->get();
```

### Query Routes Directly

```php
use Birdcar\LabelTree\Models\LabelRoute;

// Find routes by pattern
LabelRoute::wherePathMatches('priority.*')->get();

// Find descendants of a path
LabelRoute::whereDescendantOf('priority')->get();

// Find ancestors of a path
LabelRoute::whereAncestorOf('priority.high.critical')->get();
```

## Documentation

Full documentation available at [birdcar.github.io/laravel-label-tree](https://birdcar.github.io/laravel-label-tree)

- [Installation](https://birdcar.github.io/laravel-label-tree/#/installation)
- [Configuration](https://birdcar.github.io/laravel-label-tree/#/configuration)
- [Models & Relationships](https://birdcar.github.io/laravel-label-tree/#/models)
- [HasLabels Trait](https://birdcar.github.io/laravel-label-tree/#/traits)
- [Query Scopes & Patterns](https://birdcar.github.io/laravel-label-tree/#/query-scopes)
- [CLI Commands](https://birdcar.github.io/laravel-label-tree/#/commands)
- [Architecture](https://birdcar.github.io/laravel-label-tree/#/architecture)

## Requirements

- PHP 8.3+
- Laravel 11.0+ or 12.0+
- SQLite, MySQL 8.0+, or PostgreSQL 14+

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a PR.

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Run tests: `./vendor/bin/pest`
4. Run linting: `./vendor/bin/pint && ./vendor/bin/phpstan analyse`
5. Commit your changes with a descriptive message
6. Push to your branch and create a Pull Request

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
