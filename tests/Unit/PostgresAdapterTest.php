<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Query\PostgresAdapter;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    $this->adapter = new PostgresAdapter;
});

it('uses regex operator for pattern matching when no ltree', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return false;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->withArgs(function ($sql, $bindings) {
            return $sql === 'path ~ ?' && is_array($bindings) && count($bindings) === 1;
        })
        ->andReturnSelf();

    $adapter->wherePathMatches($query, 'path', '*.bug');
});

it('uses lquery syntax when ltree available', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return true;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->withArgs(function ($sql, $bindings) {
            return $sql === 'path::ltree ~ ?::lquery' && is_array($bindings) && count($bindings) === 1;
        })
        ->andReturnSelf();

    $adapter->wherePathMatches($query, 'path', '*.bug');
});

it('applies LIKE pattern directly', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.%')
        ->andReturnSelf();

    $this->adapter->wherePathLike($query, 'path', 'priority.%');
});

it('finds ancestors using ltree operator when available', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return true;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('?::ltree <@ path::ltree', ['a.b.c'])
        ->andReturnSelf();

    $adapter->whereAncestorOf($query, 'path', 'a.b.c');
});

it('finds ancestors using whereIn when no ltree', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return false;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('path', ['a', 'a.b'])
        ->andReturnSelf();

    $adapter->whereAncestorOf($query, 'path', 'a.b.c');
});

it('returns empty result for root path ancestors', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return false;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('1 = 0')
        ->andReturnSelf();

    $adapter->whereAncestorOf($query, 'path', 'root');
});

it('finds descendants using ltree operator when available', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return true;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('path::ltree <@ ?::ltree', ['priority.high'])
        ->andReturnSelf();

    $adapter->whereDescendantOf($query, 'path', 'priority.high');
});

it('finds descendants using LIKE when no ltree', function (): void {
    $adapter = new class extends PostgresAdapter
    {
        public function hasLtreeSupport(): bool
        {
            return false;
        }
    };

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.high.%')
        ->andReturnSelf();

    $adapter->whereDescendantOf($query, 'path', 'priority.high');
});
