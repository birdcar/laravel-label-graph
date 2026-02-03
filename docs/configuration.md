# Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=label-graph-config
```

This creates `config/label-graph.php`:

```php
return [
    'tables' => [
        'labels' => 'labels',
        'relationships' => 'label_relationships',
        'routes' => 'label_routes',
        'labelables' => 'labelables',
    ],
];
```

## Table Names

You can customize table names if they conflict with existing tables:

```php
'tables' => [
    'labels' => 'my_labels',
    'relationships' => 'my_label_relationships',
    'routes' => 'my_label_routes',
    'labelables' => 'my_labelables',
],
```

> **Important**: Change table names **before** running migrations. If you need to change them after, you'll need to manually rename the tables.

## Environment-Specific Configuration

You can use environment variables for different table names per environment:

```php
'tables' => [
    'labels' => env('LABEL_TREE_LABELS_TABLE', 'labels'),
    'relationships' => env('LABEL_TREE_RELATIONSHIPS_TABLE', 'label_relationships'),
    'routes' => env('LABEL_TREE_ROUTES_TABLE', 'label_routes'),
    'labelables' => env('LABEL_TREE_LABELABLES_TABLE', 'labelables'),
],
```
