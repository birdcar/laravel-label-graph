# CLI Commands

Laravel Label Graph provides Artisan commands for managing labels, relationships, and routes.

## Installation

```bash
php artisan label-graph:install
```

Publishes migrations and config, then runs migrations.

### PostgreSQL ltree Extension

For PostgreSQL users, install the ltree extension for significantly improved query performance:

```bash
php artisan label-graph:install-ltree
```

This enables native lquery/ltxtquery pattern matching and GiST index support.

Options:
- `--check`: Only check if ltree is installed, do not install

```bash
# Check status
php artisan label-graph:install-ltree --check

# Install extension
php artisan label-graph:install-ltree
```

> **Note**: Installing the extension requires CREATE privilege on the database. For managed databases (AWS RDS, etc.), you may need to enable the extension through the provider's dashboard.

## Labels

### List Labels

```bash
php artisan label-graph:label:list
```

### Create Label

```bash
php artisan label-graph:label:create "Priority"
php artisan label-graph:label:create "High" --color="#ff0000" --description="High priority"
```

Options:
- `--slug`: Custom slug (auto-generated from name if omitted)
- `--color`: Color code
- `--icon`: Icon identifier
- `--description`: Description text

### Update Label

```bash
php artisan label-graph:label:update priority --name="Priorities"
php artisan label-graph:label:update priority --color="#0000ff"
```

### Delete Label

```bash
php artisan label-graph:label:delete priority
```

> **Warning**: Deleting a label also removes all its relationships.

## Relationships

### List Relationships

```bash
php artisan label-graph:relationship:list
```

### Create Relationship

```bash
php artisan label-graph:relationship:create priority high
```

Creates an edge from "priority" (parent) to "high" (child). Labels can be specified by slug or ID.

### Delete Relationship

```bash
php artisan label-graph:relationship:delete priority high
```

If routes have attachments, you'll be prompted to choose:
- Cancel
- Cascade (delete attachments)
- Replace (migrate attachments to another route)

## Routes

### List Routes

```bash
php artisan label-graph:route:list
```

Shows all materialized paths with depth and attachment counts.

### Regenerate Routes

```bash
php artisan label-graph:route:regenerate
```

Rebuilds all routes from relationships. Useful after manual database changes.

### Prune Orphaned Routes

```bash
php artisan label-graph:route:prune
```

Removes routes that no longer correspond to valid paths in the graph.

Options:
- `--dry-run`: Show what would be pruned without deleting
- `--force`: Skip confirmation prompt

## Validation

### Validate Graph

```bash
php artisan label-graph:validate
```

Checks for:
- Cycles in the graph
- Orphaned routes
- Missing routes
- Invalid relationships

## Visualization

### Visualize Graph

```bash
php artisan label-graph:visualize
```

Outputs the label graph in ASCII art format:

```
priority
├── high
│   └── critical
└── low

type
├── bug
└── feature
```

Options:
- `--format`: Output format (ascii, mermaid, dot)
- `--output`: Save to file instead of stdout
