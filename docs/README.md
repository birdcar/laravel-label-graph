# Laravel Label Graph

Welcome to the Laravel Label Graph documentation. This package provides hierarchical labels stored as a directed acyclic graph (DAG) with materialized path routes.

## Key Differentiators

- **Multi-parent DAG**: Labels can have multiple parents (unlike trees)
- **lquery Patterns**: PostgreSQL-style pattern matching (`priority.*`, `*.bug`, `*{2,3}`)
- **Materialized Paths**: Pre-computed routes for O(1) ancestry checks
- **Polymorphic**: Attach labels to any Eloquent model

## Quick Decision

- Need hierarchy with **multiple parents**? → Use this package
- Need **pattern matching** queries? → Use this package
- Need **simple flat tags**? → Use spatie/laravel-tags
- Need **single-parent tree**? → Consider kalnoy/nestedset

See [When to Use](when-to-use.md) for detailed guidance.

## Features

- **Hierarchical Labels**: Store labels as a DAG with multiple parents/children
- **Materialized Paths**: Fast query performance with pre-computed routes
- **lquery Pattern Matching**: PostgreSQL-style patterns with wildcards, quantifiers, and alternatives
- **ltxtquery Text Search**: Boolean full-text-search-like label matching
- **Ltree Functions**: `nlevel()`, `subpath()`, `lca()`, and more
- **Multi-Database**: PostgreSQL (with optional ltree), MySQL 8+, SQLite
- **Polymorphic Labels**: Attach routes to any Eloquent model

## Quick Links

- [When to Use](when-to-use.md) - Decision guide for package selection
- [Package Comparison](comparison.md) - Feature comparison vs alternatives
- [Installation](installation.md) - Get started in 5 minutes
- [Implementation Brief](llm-implementation-brief.md) - Complete integration guide
- [HasLabels Trait](traits.md) - Add labels to any model
- [Query Patterns](query-scopes.md) - Find labels efficiently
- [Query Cookbook](llm-query-cookbook.md) - 20+ query examples
- [Architecture](architecture.md) - Understand how it works
- [Benchmarks](BENCHMARKS.md) - Performance data
