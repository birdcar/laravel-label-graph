# Benchmark Results

These benchmarks are automatically generated on each release via GitHub Actions.

## Running Locally

```bash
# Run benchmarks against default database (SQLite)
composer benchmark

# Run benchmarks against specific database
DB_CONNECTION=mysql DB_HOST=127.0.0.1 composer benchmark
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 composer benchmark
```

## Results

Results are updated automatically when a new release is published. See the [benchmark workflow runs](../../actions/workflows/benchmarks.yml) for detailed results.

### Latest Benchmark Runs

| Database | Status |
|----------|--------|
| SQLite | - |
| MySQL 8.4 | - |
| PostgreSQL 17 | - |

*Results will be populated after the first release with benchmarks enabled.*
