<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Query\PathQueryAdapter;
use Birdcar\LabelGraph\Query\PostgresAdapter;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    // Skip if not PostgreSQL with ltree
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL required');
    }

    $adapter = app(PathQueryAdapter::class);
    if (! $adapter instanceof PostgresAdapter || ! $adapter->hasLtreeSupport()) {
        $this->markTestSkipped('PostgreSQL with ltree extension required');
    }
});

describe('PostgreSQL ltree Detection', function (): void {
    it('detects ltree extension when installed', function (): void {
        $adapter = app(PostgresAdapter::class);

        expect($adapter->hasLtreeSupport())->toBeTrue();
    })->group('pgsql-ltree');

    it('reports array operators as supported', function (): void {
        $adapter = app(PostgresAdapter::class);

        expect($adapter->supportsArrayOperators())->toBeTrue();
    })->group('pgsql-ltree');
});

describe('PostgreSQL ltree SQL Generation', function (): void {
    it('generates ltree cast in wherePathMatches', function (): void {
        $query = LabelRoute::wherePathMatches('status.*');
        $sql = $query->toSql();

        expect($sql)->toContain('::ltree');
        expect($sql)->toContain('::lquery');
    })->group('pgsql-ltree');

    it('generates ltree cast in whereAncestorOf', function (): void {
        $query = LabelRoute::whereAncestorOf('a.b.c');
        $sql = $query->toSql();

        expect($sql)->toContain('::ltree');
        expect($sql)->toContain('<@');
    })->group('pgsql-ltree');

    it('generates ltree cast in whereDescendantOf', function (): void {
        $query = LabelRoute::whereDescendantOf('a.b.c');
        $sql = $query->toSql();

        expect($sql)->toContain('::ltree');
        expect($sql)->toContain('<@');
    })->group('pgsql-ltree');

    it('generates ltxtquery cast in wherePathMatchesText', function (): void {
        $query = LabelRoute::wherePathMatchesText('status & open');
        $sql = $query->toSql();

        expect($sql)->toContain('::ltree');
        expect($sql)->toContain('::ltxtquery');
        expect($sql)->toContain('@');
    })->group('pgsql-ltree');
});

describe('PostgreSQL ltree Native Functions', function (): void {
    beforeEach(function (): void {
        LabelRoute::create(['path' => 'a.b.c.d', 'depth' => 3]);
    });

    it('executes nlevel natively', function (): void {
        $result = DB::selectOne("SELECT nlevel('a.b.c.d'::ltree) as level");

        expect($result->level)->toBe(4);
    })->group('pgsql-ltree');

    it('executes subpath natively', function (): void {
        $result = DB::selectOne("SELECT subpath('a.b.c.d'::ltree, 1, 2)::text as sub");

        expect($result->sub)->toBe('b.c');
    })->group('pgsql-ltree');

    it('executes lca natively', function (): void {
        $result = DB::selectOne("SELECT lca('a.b.c'::ltree, 'a.b.d'::ltree)::text as ancestor");

        expect($result->ancestor)->toBe('a.b');
    })->group('pgsql-ltree');

    it('executes index natively', function (): void {
        $result = DB::selectOne("SELECT index('a.b.c.b.c'::ltree, 'b.c'::ltree) as idx");

        expect($result->idx)->toBe(1);
    })->group('pgsql-ltree');
});

describe('PostgreSQL ltree Array Operators', function (): void {
    beforeEach(function (): void {
        LabelRoute::create(['path' => 'a', 'depth' => 0]);
        LabelRoute::create(['path' => 'a.b', 'depth' => 1]);
        LabelRoute::create(['path' => 'a.b.c', 'depth' => 2]);
        LabelRoute::create(['path' => 'x.y', 'depth' => 1]);
    });

    it('finds routes with ancestor in array', function (): void {
        $routes = LabelRoute::wherePathInAncestors(['a', 'x'])->get();

        expect($routes)->toHaveCount(3); // a.b, a.b.c, x.y
    })->group('pgsql-ltree');

    it('finds first ancestor from candidates', function (): void {
        $result = LabelRoute::firstAncestorFrom('a.b.c', ['a', 'a.b', 'x']);

        expect($result)->toBe('a'); // First in array order
    })->group('pgsql-ltree');

    it('returns null when no ancestor found', function (): void {
        $result = LabelRoute::firstAncestorFrom('a.b.c', ['x', 'y', 'z']);

        expect($result)->toBeNull();
    })->group('pgsql-ltree');
});

describe('PostgreSQL GiST Index', function (): void {
    it('can create GiST index on ltree column', function (): void {
        $tableName = config('label-graph.tables.routes', 'label_routes');

        // Create index
        DB::statement("CREATE INDEX IF NOT EXISTS test_gist_idx ON {$tableName} USING GIST (path::ltree)");

        // Verify it exists
        $index = DB::selectOne(
            'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$tableName, 'test_gist_idx']
        );

        expect($index)->not->toBeNull();

        // Cleanup
        DB::statement('DROP INDEX IF EXISTS test_gist_idx');
    })->group('pgsql-ltree');
});
