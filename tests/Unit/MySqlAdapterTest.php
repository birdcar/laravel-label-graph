<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Query\MySqlAdapter;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    $this->adapter = new MySqlAdapter;
});

it('has no ltree support', function (): void {
    expect($this->adapter->hasLtreeSupport())->toBeFalse();
});

it('uses REGEXP for pattern matching', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->withArgs(function ($sql, $bindings) {
            return $sql === 'path REGEXP ?' && is_array($bindings) && count($bindings) === 1;
        })
        ->andReturnSelf();

    $this->adapter->wherePathMatches($query, 'path', '*.bug');
});

it('applies LIKE pattern directly', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.%')
        ->andReturnSelf();

    $this->adapter->wherePathLike($query, 'path', 'priority.%');
});

it('finds ancestors using whereIn with prefixes', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('path', ['a', 'a.b', 'a.b.c'])
        ->andReturnSelf();

    $this->adapter->whereAncestorOf($query, 'path', 'a.b.c.d');
});

it('returns empty result for root path ancestors', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('1 = 0')
        ->andReturnSelf();

    $this->adapter->whereAncestorOf($query, 'path', 'root');
});

it('finds descendants using LIKE prefix', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.high.%')
        ->andReturnSelf();

    $this->adapter->whereDescendantOf($query, 'path', 'priority.high');
});
