<?php

declare(strict_types=1);

use Birdcar\LabelTree\Ltree\LtreeExpression;

describe('LtreeExpression::nlevel', function (): void {
    it('generates PostgreSQL ltree expression when available', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->nlevel('path');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('nlevel')
            ->toContain('ltree');
    });

    it('generates PostgreSQL string expression without ltree', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: false);
        $result = $expr->nlevel('path');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('LENGTH')
            ->toContain('REPLACE');
    });

    it('generates MySQL expression', function (): void {
        $expr = new LtreeExpression('mysql', hasLtree: false);
        $result = $expr->nlevel('path');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('LENGTH')
            ->toContain('REPLACE');
    });

    it('generates SQLite UDF expression', function (): void {
        $expr = new LtreeExpression('sqlite', hasLtree: false);
        $result = $expr->nlevel('path');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('ltree_nlevel');
    });
});

describe('LtreeExpression::subpath', function (): void {
    it('generates PostgreSQL ltree expression when available', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->subpath('path', 1, 2);

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('subpath')
            ->toContain('ltree');
    });

    it('generates SQLite UDF expression', function (): void {
        $expr = new LtreeExpression('sqlite', hasLtree: false);
        $result = $expr->subpath('path', 1);

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('ltree_subpath');
    });

    it('generates SQLite UDF expression with length', function (): void {
        $expr = new LtreeExpression('sqlite', hasLtree: false);
        $result = $expr->subpath('path', 1, 2);

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('ltree_subpath_len');
    });

    it('throws for negative offset without ltree', function (): void {
        $expr = new LtreeExpression('mysql', hasLtree: false);

        expect(fn () => $expr->subpath('path', -1))
            ->toThrow(RuntimeException::class);
    });

    it('throws for negative length without ltree', function (): void {
        $expr = new LtreeExpression('mysql', hasLtree: false);

        expect(fn () => $expr->subpath('path', 0, -1))
            ->toThrow(RuntimeException::class);
    });
});

describe('LtreeExpression::subltree', function (): void {
    it('returns empty string expression for start >= end', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->subltree('path', 2, 2);

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toBe("''");
    });

    it('converts to subpath call', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->subltree('path', 1, 3);

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('subpath');
    });
});

describe('LtreeExpression::index', function (): void {
    it('generates PostgreSQL ltree expression when available', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->index('path', 'a.b');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('index')
            ->toContain('ltree');
    });

    it('generates SQLite UDF expression', function (): void {
        $expr = new LtreeExpression('sqlite', hasLtree: false);
        $result = $expr->index('path', 'a.b');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('ltree_index');
    });

    it('throws for MySQL without ltree', function (): void {
        $expr = new LtreeExpression('mysql', hasLtree: false);

        expect(fn () => $expr->index('path', 'a.b'))
            ->toThrow(RuntimeException::class);
    });
});

describe('LtreeExpression::concat', function (): void {
    it('generates PostgreSQL ltree concat when available', function (): void {
        $expr = new LtreeExpression('pgsql', hasLtree: true);
        $result = $expr->concat('path1', 'path2');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('||')
            ->toContain('ltree');
    });

    it('generates MySQL CONCAT', function (): void {
        $expr = new LtreeExpression('mysql', hasLtree: false);
        $result = $expr->concat('path1', 'path2');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('CONCAT');
    });

    it('generates SQLite concatenation', function (): void {
        $expr = new LtreeExpression('sqlite', hasLtree: false);
        $result = $expr->concat('path1', 'path2');

        expect($result->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()))
            ->toContain('||');
    });
});
