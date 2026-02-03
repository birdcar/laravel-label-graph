<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\InvalidPathException;
use Birdcar\LabelTree\Ltree\Ltree;

describe('Ltree::nlevel', function (): void {
    it('returns 0 for empty path', function (): void {
        expect(Ltree::nlevel(''))->toBe(0);
    });

    it('returns 1 for single label', function (): void {
        expect(Ltree::nlevel('foo'))->toBe(1);
    });

    it('returns 3 for three labels', function (): void {
        expect(Ltree::nlevel('a.b.c'))->toBe(3);
    });

    it('returns 5 for five labels', function (): void {
        expect(Ltree::nlevel('a.b.c.d.e'))->toBe(5);
    });
});

describe('Ltree::subpath', function (): void {
    it('returns empty for empty path', function (): void {
        expect(Ltree::subpath('', 0))->toBe('');
    });

    it('returns from offset to end', function (): void {
        expect(Ltree::subpath('a.b.c.d', 1))->toBe('b.c.d');
    });

    it('returns from offset with length', function (): void {
        expect(Ltree::subpath('a.b.c.d', 0, 2))->toBe('a.b');
        expect(Ltree::subpath('a.b.c.d', 1, 2))->toBe('b.c');
    });

    it('handles negative offset', function (): void {
        expect(Ltree::subpath('a.b.c.d', -2))->toBe('c.d');
        expect(Ltree::subpath('a.b.c.d', -1))->toBe('d');
    });

    it('handles negative length', function (): void {
        expect(Ltree::subpath('a.b.c.d', 0, -1))->toBe('a.b.c');
        expect(Ltree::subpath('a.b.c.d', 1, -1))->toBe('b.c');
    });

    it('returns empty for out of bounds offset', function (): void {
        expect(Ltree::subpath('a.b.c', 10))->toBe('');
    });

    it('returns full path for offset 0 with no length', function (): void {
        expect(Ltree::subpath('a.b.c', 0))->toBe('a.b.c');
    });
});

describe('Ltree::subltree', function (): void {
    it('returns empty for start >= end', function (): void {
        expect(Ltree::subltree('a.b.c', 2, 2))->toBe('');
        expect(Ltree::subltree('a.b.c', 3, 2))->toBe('');
    });

    it('returns subpath between start and end', function (): void {
        expect(Ltree::subltree('a.b.c.d', 1, 3))->toBe('b.c');
    });

    it('returns from start to end-1', function (): void {
        expect(Ltree::subltree('a.b.c.d', 0, 2))->toBe('a.b');
    });

    it('returns empty for empty path', function (): void {
        expect(Ltree::subltree('', 0, 2))->toBe('');
    });
});

describe('Ltree::index', function (): void {
    it('returns -1 for empty path', function (): void {
        expect(Ltree::index('', 'a'))->toBe(-1);
    });

    it('returns -1 for empty subpath', function (): void {
        expect(Ltree::index('a.b.c', ''))->toBe(-1);
    });

    it('returns -1 when subpath not found', function (): void {
        expect(Ltree::index('a.b.c', 'x'))->toBe(-1);
    });

    it('returns position of first occurrence', function (): void {
        expect(Ltree::index('a.b.c.b.c', 'b.c'))->toBe(1);
    });

    it('returns position for single label', function (): void {
        expect(Ltree::index('a.b.c', 'b'))->toBe(1);
    });

    it('returns 0 for match at start', function (): void {
        expect(Ltree::index('a.b.c', 'a'))->toBe(0);
    });

    it('respects offset parameter', function (): void {
        expect(Ltree::index('a.b.c.b.c', 'b.c', 2))->toBe(3);
    });

    it('handles negative offset', function (): void {
        expect(Ltree::index('a.b.c.b.c', 'b.c', -3))->toBe(3);
    });

    it('returns -1 when subpath longer than path', function (): void {
        expect(Ltree::index('a', 'a.b.c'))->toBe(-1);
    });
});

describe('Ltree::lca', function (): void {
    it('returns empty for empty array', function (): void {
        expect(Ltree::lca([]))->toBe('');
    });

    it('returns path for single path', function (): void {
        expect(Ltree::lca(['a.b.c']))->toBe('a.b.c');
    });

    it('returns common ancestor for two paths', function (): void {
        expect(Ltree::lca(['a.b.c', 'a.b.d']))->toBe('a.b');
    });

    it('returns common ancestor for multiple paths', function (): void {
        expect(Ltree::lca(['a.b.c', 'a.b.d', 'a.b.e.f']))->toBe('a.b');
    });

    it('returns empty for paths with no common ancestor', function (): void {
        expect(Ltree::lca(['a.b', 'c.d']))->toBe('');
    });

    it('returns first label for paths sharing only first label', function (): void {
        expect(Ltree::lca(['a.b', 'a.c']))->toBe('a');
    });

    it('accepts collection', function (): void {
        expect(Ltree::lca(collect(['a.b.c', 'a.b.d'])))->toBe('a.b');
    });

    it('filters empty paths', function (): void {
        expect(Ltree::lca(['', 'a.b.c', '']))->toBe('a.b.c');
    });
});

describe('Ltree::text2ltree', function (): void {
    it('returns empty for empty string', function (): void {
        expect(Ltree::text2ltree(''))->toBe('');
    });

    it('trims whitespace', function (): void {
        expect(Ltree::text2ltree('  a.b.c  '))->toBe('a.b.c');
    });

    it('returns valid path unchanged', function (): void {
        expect(Ltree::text2ltree('valid.path'))->toBe('valid.path');
    });

    it('throws for consecutive dots', function (): void {
        expect(fn () => Ltree::text2ltree('invalid..path'))
            ->toThrow(InvalidPathException::class);
    });

    it('throws for leading dot', function (): void {
        expect(fn () => Ltree::text2ltree('.invalid'))
            ->toThrow(InvalidPathException::class);
    });

    it('throws for trailing dot', function (): void {
        expect(fn () => Ltree::text2ltree('invalid.'))
            ->toThrow(InvalidPathException::class);
    });

    it('throws for invalid characters', function (): void {
        expect(fn () => Ltree::text2ltree('invalid!path'))
            ->toThrow(InvalidPathException::class);
    });

    it('accepts underscores and hyphens', function (): void {
        expect(Ltree::text2ltree('foo_bar.baz-qux'))->toBe('foo_bar.baz-qux');
    });

    it('accepts alphanumeric labels', function (): void {
        expect(Ltree::text2ltree('abc123.xyz789'))->toBe('abc123.xyz789');
    });
});

describe('Ltree::ltree2text', function (): void {
    it('returns path unchanged', function (): void {
        expect(Ltree::ltree2text('a.b.c'))->toBe('a.b.c');
    });

    it('returns empty for empty path', function (): void {
        expect(Ltree::ltree2text(''))->toBe('');
    });
});

describe('Ltree::concat', function (): void {
    it('concatenates two paths', function (): void {
        expect(Ltree::concat('a.b', 'c.d'))->toBe('a.b.c.d');
    });

    it('returns second when first is empty', function (): void {
        expect(Ltree::concat('', 'a.b'))->toBe('a.b');
    });

    it('returns first when second is empty', function (): void {
        expect(Ltree::concat('a.b', ''))->toBe('a.b');
    });

    it('returns empty when both are empty', function (): void {
        expect(Ltree::concat('', ''))->toBe('');
    });
});
