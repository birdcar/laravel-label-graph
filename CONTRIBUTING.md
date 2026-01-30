# Contributing

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer
- Docker (for MySQL/PostgreSQL testing)

### Install Dependencies

```bash
composer install
```

### Running Tests

```bash
# SQLite (default, no setup required)
composer test

# All databases (requires Docker)
docker-compose up -d
composer test:all
```

## Local Database Testing

Tests run against SQLite by default. For MySQL and PostgreSQL testing, use Docker:

```bash
docker-compose up -d
composer test:all
```

### Database Credentials

| Setting | Value |
|---------|-------|
| Username | `labeltree` |
| Password | `labeltree` |
| Database | `laravel_label_tree` |
| MySQL Port | `13306` |
| PostgreSQL Port | `15432` |

## Code Quality

Before submitting a PR:

```bash
./vendor/bin/pint           # Fix formatting
./vendor/bin/phpstan analyse --memory-limit=512M
./vendor/bin/pest           # Run tests
```
