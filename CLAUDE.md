# Laravel Label Graph

PHP package for hierarchical labels as a DAG with materialized path routes.

## Commands

```bash
# Validate (run in order - linter fixes issues that would fail other checks)
./vendor/bin/pint              # Fix formatting first
./vendor/bin/phpstan analyse --memory-limit=512M
./vendor/bin/pest

# Manual testing
./vendor/bin/testbench tinker
```

## Architecture

- **Models**: `Label`, `LabelRelationship`, `LabelRoute` - all use ULIDs
- **Services**: `CycleDetector` (DFS), `RouteGenerator` (graph traversal)
- **Observer**: Triggers route regeneration on relationship changes
- **Validation**: Cycle detection runs in `creating` event, rejects before save

## Commits

Use `/commit` command. Break changes into logical, chronological commits that explain **why** decisions were made. Each commit should be atomic and build on previous ones.

Example progression:
1. `chore:` foundation/config
2. `feat(models):` core entities
3. `feat(validation):` business rules
4. `test:` coverage

## Testing

Tests use Orchestra Testbench with SQLite (foreign keys enabled). Test migrations live in `tests/database/migrations/`.

Run `pint` before `pest` - formatting fixes are cheaper than test output in context.

## Key Patterns

- Config-driven table names (`config('label-graph.tables.labels')`)
- Transaction-wrapped route regeneration for atomicity
- DFS cycle detection checks reachability from child→parent before allowing edge
