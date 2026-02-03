<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\InvalidPathException;
use Birdcar\LabelGraph\Ltree\Ltree;

/**
 * Tests for PRD Phase 3 Acceptance Criteria.
 */
describe('Acceptance: subltree extracts subpath from start to end-1', function (): void {
    it('subltree(a.b.c.d, 1, 3) returns b.c', function (): void {
        expect(Ltree::subltree('a.b.c.d', 1, 3))->toBe('b.c');
    });
});

describe('Acceptance: subpath extracts from offset with optional length', function (): void {
    it('subpath(a.b.c.d, 0, 2) returns a.b', function (): void {
        expect(Ltree::subpath('a.b.c.d', 0, 2))->toBe('a.b');
    });

    it('subpath(a.b.c.d, -2) returns c.d', function (): void {
        expect(Ltree::subpath('a.b.c.d', -2))->toBe('c.d');
    });

    it('subpath(a.b.c.d, 1) returns b.c.d', function (): void {
        expect(Ltree::subpath('a.b.c.d', 1))->toBe('b.c.d');
    });
});

describe('Acceptance: nlevel returns number of labels', function (): void {
    it('nlevel(a.b.c) returns 3', function (): void {
        expect(Ltree::nlevel('a.b.c'))->toBe(3);
    });

    it('nlevel empty string returns 0', function (): void {
        expect(Ltree::nlevel(''))->toBe(0);
    });
});

describe('Acceptance: index finds subpath position', function (): void {
    it('index(a.b.c.b.c, b.c) returns 1', function (): void {
        expect(Ltree::index('a.b.c.b.c', 'b.c'))->toBe(1);
    });

    it('index(a.b.c, x) returns -1', function (): void {
        expect(Ltree::index('a.b.c', 'x'))->toBe(-1);
    });

    it('index(a.b.c.b.c, b.c, 2) returns 3', function (): void {
        expect(Ltree::index('a.b.c.b.c', 'b.c', 2))->toBe(3);
    });
});

describe('Acceptance: lca computes longest common ancestor', function (): void {
    it('lca([a.b.c, a.b.d, a.b.e.f]) returns a.b', function (): void {
        expect(Ltree::lca(['a.b.c', 'a.b.d', 'a.b.e.f']))->toBe('a.b');
    });

    it('lca([a.b, c.d]) returns empty string', function (): void {
        expect(Ltree::lca(['a.b', 'c.d']))->toBe('');
    });
});

describe('Acceptance: text2ltree validates and normalizes path', function (): void {
    it('text2ltree(valid.path) returns normalized path', function (): void {
        expect(Ltree::text2ltree('valid.path'))->toBe('valid.path');
    });

    it('text2ltree(invalid..path) throws InvalidPathException', function (): void {
        expect(fn () => Ltree::text2ltree('invalid..path'))
            ->toThrow(InvalidPathException::class);
    });
});

describe('Acceptance: Static helpers work without database connection', function (): void {
    it('nlevel works standalone', function (): void {
        expect(Ltree::nlevel('a.b.c.d.e'))->toBe(5);
    });

    it('subpath works standalone', function (): void {
        expect(Ltree::subpath('a.b.c.d', 1, 2))->toBe('b.c');
    });

    it('index works standalone', function (): void {
        expect(Ltree::index('a.b.c.d', 'c'))->toBe(2);
    });

    it('lca works standalone', function (): void {
        expect(Ltree::lca(['x.y.z', 'x.y.w']))->toBe('x.y');
    });

    it('text2ltree works standalone', function (): void {
        expect(Ltree::text2ltree('  foo.bar  '))->toBe('foo.bar');
    });

    it('concat works standalone', function (): void {
        expect(Ltree::concat('a.b', 'c.d'))->toBe('a.b.c.d');
    });
});
