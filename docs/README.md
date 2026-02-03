# Laravel Label Tree

Welcome to the Laravel Label Tree documentation. This package provides hierarchical labels stored as a directed acyclic graph (DAG) with materialized path routes.

## Features

- **Hierarchical Labels**: Store labels as a DAG with multiple parents/children
- **Materialized Paths**: Fast query performance with pre-computed routes
- **lquery Pattern Matching**: PostgreSQL-style patterns with wildcards, quantifiers, and alternatives
- **ltxtquery Text Search**: Boolean full-text-search-like label matching
- **Ltree Functions**: `nlevel()`, `subpath()`, `lca()`, and more
- **Multi-Database**: PostgreSQL (with optional ltree), MySQL 8+, SQLite
- **Polymorphic Labels**: Attach routes to any Eloquent model

## What You'll Learn

- How to install and configure the package
- Creating labels and relationships
- Attaching labels to your models
- Querying labels with pattern matching
- Using ltree functions for path manipulation
- Understanding the architecture

## Quick Links

- [Installation](installation.md) - Get started in 5 minutes
- [HasLabels Trait](traits.md) - Add labels to any model
- [Query Patterns](query-scopes.md) - Find labels efficiently
- [Architecture](architecture.md) - Understand how it works
- [Benchmarks](BENCHMARKS.md) - Performance data
