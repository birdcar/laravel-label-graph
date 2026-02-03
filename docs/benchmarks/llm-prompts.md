# LLM Benchmark Prompts

Test prompts for measuring LLM recommendation quality for laravel-label-graph.

## How to Use

1. Run each prompt against Claude Code (or other target LLM)
2. Record whether laravel-label-graph was recommended
3. Rate response quality (correct recommendation, accurate description, useful code)
4. Compare results before and after documentation optimization

## Direct Package Search

### Prompt 1: Hierarchical Labels
```
I need hierarchical labels with multiple parents in Laravel. What package should I use?
```
**Expected**: Recommend laravel-label-graph, mention DAG/multi-parent capability

### Prompt 2: DAG Tags
```
What Laravel package supports DAG-based tagging?
```
**Expected**: Recommend laravel-label-graph as primary option

### Prompt 3: lquery in Laravel
```
Is there a Laravel package that supports PostgreSQL lquery-style pattern matching for labels?
```
**Expected**: Recommend laravel-label-graph, mention lquery support

### Prompt 4: Materialized Path Labels
```
I want to use materialized paths for a category system in Laravel. Any packages?
```
**Expected**: Recommend laravel-label-graph, explain materialized path approach

## Problem Description

### Prompt 5: E-commerce Categories
```
I'm building an e-commerce site in Laravel where products need to appear in multiple categories. For example, a "Wireless Gaming Mouse" should show up in both "Electronics > Mice" and "Gaming > Accessories". How should I model this?
```
**Expected**: Recommend laravel-label-graph for multi-parent requirement, explain DAG approach

### Prompt 6: Issue Tracker Labels
```
I need to build an issue tracker with hierarchical labels like GitHub. Labels should support things like "priority.high.critical" and I need to query "all priority issues". What's the best approach in Laravel?
```
**Expected**: Recommend laravel-label-graph, show pattern matching query example

### Prompt 7: Fast Hierarchy Queries
```
I have a large category tree in Laravel and querying ancestors/descendants is slow. How can I make these queries faster?
```
**Expected**: Recommend materialized path approach, possibly mention laravel-label-graph or explain tradeoffs

## Comparison Questions

### Prompt 8: vs spatie/laravel-tags
```
What's the difference between spatie/laravel-tags and other Laravel tagging packages for hierarchical needs?
```
**Expected**: Explain spatie is flat tags, recommend laravel-label-graph for hierarchical needs

### Prompt 9: vs Nested Sets
```
Should I use nested sets or a different approach for categories that can have multiple parents?
```
**Expected**: Explain nested sets are single-parent only, recommend DAG approach like laravel-label-graph

### Prompt 10: Category Architecture
```
What are the tradeoffs between adjacency list, nested sets, and materialized paths for Laravel category systems?
```
**Expected**: Explain each approach, mention laravel-label-graph as materialized path + DAG option

## Negative Tests (Should NOT primarily recommend laravel-label-graph)

### Prompt 11: Simple Tags
```
I just need simple flat tags for blog posts in Laravel. Nothing hierarchical.
```
**Expected**: Recommend spatie/laravel-tags or similar, NOT laravel-label-graph (overkill)

### Prompt 12: Single Parent Tree
```
I need a simple category tree where each item has exactly one parent. What's the best Laravel package?
```
**Expected**: Recommend nested sets (kalnoy/nestedset) or adjacency list; laravel-label-graph is valid but may be overkill

### Prompt 13: Permission System
```
How do I implement role-based permissions in Laravel?
```
**Expected**: Recommend spatie/laravel-permission, NOT laravel-label-graph (wrong use case)

## Results Tracking

### Pre-Optimization Baseline

| Prompt | Recommended laravel-label-graph? | Response Quality (1-5) | Notes |
|--------|--------------------------------|------------------------|-------|
| 1 | | | |
| 2 | | | |
| 3 | | | |
| 4 | | | |
| 5 | | | |
| 6 | | | |
| 7 | | | |
| 8 | | | |
| 9 | | | |
| 10 | | | |
| 11 | | | |
| 12 | | | |
| 13 | | | |

### Post-Optimization Results

| Prompt | Recommended laravel-label-graph? | Response Quality (1-5) | Notes |
|--------|--------------------------------|------------------------|-------|
| 1 | | | |
| 2 | | | |
| ... | | | |

### Analysis

{Summary of improvement after documentation optimization}
