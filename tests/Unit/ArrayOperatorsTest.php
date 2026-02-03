<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\UnsupportedDatabaseException;
use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Query\MySqlAdapter;
use Birdcar\LabelGraph\Query\PostgresAdapter;
use Birdcar\LabelGraph\Query\SqliteAdapter;

describe('supportsArrayOperators', function (): void {
    it('returns false for MySQL', function (): void {
        $adapter = new MySqlAdapter;
        expect($adapter->supportsArrayOperators())->toBeFalse();
    });

    it('returns false for SQLite', function (): void {
        $adapter = new SqliteAdapter;
        expect($adapter->supportsArrayOperators())->toBeFalse();
    });

    it('returns false for PostgreSQL without ltree', function (): void {
        $adapter = new PostgresAdapter;
        // In test environment (SQLite), this should return false
        // The hasLtreeSupport method will fail to connect to PostgreSQL
        expect($adapter->supportsArrayOperators())->toBeFalse();
    });
});

describe('MySQL array operators throw UnsupportedDatabaseException', function (): void {
    it('throws on wherePathHasAncestorIn', function (): void {
        $adapter = new MySqlAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->wherePathHasAncestorIn($query, 'path', ['a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on wherePathHasDescendantIn', function (): void {
        $adapter = new MySqlAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->wherePathHasDescendantIn($query, 'path', ['a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on whereAnyPathMatches', function (): void {
        $adapter = new MySqlAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->whereAnyPathMatches($query, 'path', ['a.b'], '*.b'))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on firstAncestorFrom', function (): void {
        $adapter = new MySqlAdapter;

        expect(fn () => $adapter->firstAncestorFrom('a.b.c', ['a', 'a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on firstDescendantFrom', function (): void {
        $adapter = new MySqlAdapter;

        expect(fn () => $adapter->firstDescendantFrom('a', ['a.b', 'a.b.c']))
            ->toThrow(UnsupportedDatabaseException::class);
    });
});

describe('SQLite array operators throw UnsupportedDatabaseException', function (): void {
    it('throws on wherePathHasAncestorIn', function (): void {
        $adapter = new SqliteAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->wherePathHasAncestorIn($query, 'path', ['a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on wherePathHasDescendantIn', function (): void {
        $adapter = new SqliteAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->wherePathHasDescendantIn($query, 'path', ['a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on whereAnyPathMatches', function (): void {
        $adapter = new SqliteAdapter;
        $query = LabelRoute::query();

        expect(fn () => $adapter->whereAnyPathMatches($query, 'path', ['a.b'], '*.b'))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on firstAncestorFrom', function (): void {
        $adapter = new SqliteAdapter;

        expect(fn () => $adapter->firstAncestorFrom('a.b.c', ['a', 'a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('throws on firstDescendantFrom', function (): void {
        $adapter = new SqliteAdapter;

        expect(fn () => $adapter->firstDescendantFrom('a', ['a.b', 'a.b.c']))
            ->toThrow(UnsupportedDatabaseException::class);
    });
});

describe('LabelRoute static methods', function (): void {
    it('supportsArrayOperators returns false for SQLite', function (): void {
        // In test environment (SQLite), array operators are not supported
        expect(LabelRoute::supportsArrayOperators())->toBeFalse();
    });

    it('firstAncestorFrom throws on unsupported database', function (): void {
        expect(fn () => LabelRoute::firstAncestorFrom('a.b.c', ['a', 'a.b']))
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('firstDescendantFrom throws on unsupported database', function (): void {
        expect(fn () => LabelRoute::firstDescendantFrom('a', ['a.b', 'a.b.c']))
            ->toThrow(UnsupportedDatabaseException::class);
    });
});

describe('LabelRoute query scopes throw on unsupported database', function (): void {
    it('wherePathInAncestors throws UnsupportedDatabaseException', function (): void {
        expect(fn () => LabelRoute::wherePathInAncestors(['a', 'a.b'])->get())
            ->toThrow(UnsupportedDatabaseException::class);
    });

    it('wherePathInDescendants throws UnsupportedDatabaseException', function (): void {
        expect(fn () => LabelRoute::wherePathInDescendants(['a.b.c', 'a.b.c.d'])->get())
            ->toThrow(UnsupportedDatabaseException::class);
    });
});

describe('UnsupportedDatabaseException messages', function (): void {
    it('arrayOperators includes driver name', function (): void {
        $exception = UnsupportedDatabaseException::arrayOperators('mysql');

        expect($exception->getMessage())
            ->toContain('mysql')
            ->toContain('PostgreSQL')
            ->toContain('ltree')
            ->toContain('supportsArrayOperators');
    });

    it('gistIndex includes driver name', function (): void {
        $exception = UnsupportedDatabaseException::gistIndex('sqlite');

        expect($exception->getMessage())
            ->toContain('sqlite')
            ->toContain('PostgreSQL')
            ->toContain('GiST');
    });
});
