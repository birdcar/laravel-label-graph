<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Query\Lquery\Lquery;

describe('simple label matching', function (): void {
    it('matches exact path', function (): void {
        expect(Lquery::matches('foo', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo', 'bar'))->toBeFalse();
        expect(Lquery::matches('foo', 'foo.bar'))->toBeFalse();
    });

    it('matches multi-segment exact path', function (): void {
        expect(Lquery::matches('foo.bar', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('foo.bar', 'foo'))->toBeFalse();
        expect(Lquery::matches('foo.bar', 'foo.bar.baz'))->toBeFalse();
    });
});

describe('star pattern matching', function (): void {
    it('star matches zero or more labels', function (): void {
        // *.foo should match: foo, anything.foo, a.b.foo
        expect(Lquery::matches('*.foo', 'foo'))->toBeTrue();
        expect(Lquery::matches('*.foo', 'bar.foo'))->toBeTrue();
        expect(Lquery::matches('*.foo', 'a.b.foo'))->toBeTrue();
        expect(Lquery::matches('*.foo', 'bar'))->toBeFalse();
        expect(Lquery::matches('*.foo', 'foo.bar'))->toBeFalse();
    });

    it('foo.* matches foo followed by any labels', function (): void {
        expect(Lquery::matches('foo.*', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo.*', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('foo.*', 'foo.bar.baz'))->toBeTrue();
        expect(Lquery::matches('foo.*', 'bar.foo'))->toBeFalse();
    });

    it('*.foo.* matches any path containing foo', function (): void {
        expect(Lquery::matches('*.foo.*', 'foo'))->toBeTrue();
        expect(Lquery::matches('*.foo.*', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('*.foo.*', 'bar.foo'))->toBeTrue();
        expect(Lquery::matches('*.foo.*', 'bar.foo.baz'))->toBeTrue();
        expect(Lquery::matches('*.foo.*', 'a.b.foo.c.d'))->toBeTrue();
        expect(Lquery::matches('*.foo.*', 'bar'))->toBeFalse();
        expect(Lquery::matches('*.foo.*', 'food'))->toBeFalse();
    });

    it('just star matches any path', function (): void {
        expect(Lquery::matches('*', 'foo'))->toBeTrue();
        expect(Lquery::matches('*', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('*', 'a.b.c.d'))->toBeTrue();
    });
});

describe('star quantifier matching', function (): void {
    it('*{1} matches exactly one label', function (): void {
        expect(Lquery::matches('*{1}', 'foo'))->toBeTrue();
        expect(Lquery::matches('*{1}', 'foo.bar'))->toBeFalse();
    });

    it('*{2} matches exactly two labels', function (): void {
        expect(Lquery::matches('*{2}', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('*{2}', 'foo'))->toBeFalse();
        expect(Lquery::matches('*{2}', 'foo.bar.baz'))->toBeFalse();
    });

    it('*{1,2} matches one to two labels', function (): void {
        expect(Lquery::matches('*{1,2}', 'foo'))->toBeTrue();
        expect(Lquery::matches('*{1,2}', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('*{1,2}', 'foo.bar.baz'))->toBeFalse();
    });

    it('*{2,} matches two or more labels', function (): void {
        expect(Lquery::matches('*{2,}', 'foo'))->toBeFalse();
        expect(Lquery::matches('*{2,}', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('*{2,}', 'foo.bar.baz'))->toBeTrue();
    });

    it('*{,2} matches zero to two labels', function (): void {
        expect(Lquery::matches('foo.*{,2}', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo.*{,2}', 'foo.bar'))->toBeTrue();
        expect(Lquery::matches('foo.*{,2}', 'foo.bar.baz'))->toBeTrue();
        expect(Lquery::matches('foo.*{,2}', 'foo.bar.baz.qux'))->toBeFalse();
    });
});

describe('modifier matching', function (): void {
    it('@ makes matching case-insensitive', function (): void {
        expect(Lquery::matches('foo@', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo@', 'FOO'))->toBeTrue();
        expect(Lquery::matches('foo@', 'Foo'))->toBeTrue();
        expect(Lquery::matches('foo', 'FOO'))->toBeFalse();
    });

    it('* matches label prefix', function (): void {
        expect(Lquery::matches('foo*', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo*', 'foobar'))->toBeTrue();
        expect(Lquery::matches('foo*', 'food'))->toBeTrue();
        expect(Lquery::matches('foo*', 'bar'))->toBeFalse();
        expect(Lquery::matches('foo*', 'barfoo'))->toBeFalse();
    });

    it('% matches underscore-separated words', function (): void {
        expect(Lquery::matches('foo%', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo%', 'foo_bar'))->toBeTrue();
        expect(Lquery::matches('foo%', 'foo_bar_baz'))->toBeTrue();
        expect(Lquery::matches('foo%', 'foobar'))->toBeFalse();
    });

    it('combined modifiers work together', function (): void {
        expect(Lquery::matches('foo*@', 'FOOBAR'))->toBeTrue();
        expect(Lquery::matches('foo*@', 'Food'))->toBeTrue();
    });
});

describe('group matching', function (): void {
    it('OR group matches alternatives', function (): void {
        expect(Lquery::matches('foo|bar', 'foo'))->toBeTrue();
        expect(Lquery::matches('foo|bar', 'bar'))->toBeTrue();
        expect(Lquery::matches('foo|bar', 'baz'))->toBeFalse();
    });

    it('NOT group excludes alternatives', function (): void {
        expect(Lquery::matches('!foo|bar', 'baz'))->toBeTrue();
        expect(Lquery::matches('!foo|bar', 'qux'))->toBeTrue();
        expect(Lquery::matches('!foo|bar', 'foo'))->toBeFalse();
        expect(Lquery::matches('!foo|bar', 'bar'))->toBeFalse();
    });

    it('group with quantifier', function (): void {
        // Match two labels, neither foo nor bar
        expect(Lquery::matches('!foo|bar{2}', 'baz.qux'))->toBeTrue();
        expect(Lquery::matches('!foo|bar{2}', 'foo.qux'))->toBeFalse();
    });
});

describe('complex patterns', function (): void {
    it('matches ltree documentation example', function (): void {
        // Top.*{0,2}.sport*@.!football|tennis{1,}.Russ*|Spain
        // Simplified version for testing
        $pattern = '*.sport*.*.Russ*|Spain';

        // Should match paths like: Top.sport.news.Russia
        expect(Lquery::matches($pattern, 'Top.sports.news.Russia'))->toBeTrue();
        expect(Lquery::matches($pattern, 'sport.Spain'))->toBeTrue();
    });

    it('matches path hierarchy queries', function (): void {
        // Common use cases
        expect(Lquery::matches('tech.*', 'tech'))->toBeTrue();
        expect(Lquery::matches('tech.*', 'tech.backend'))->toBeTrue();
        expect(Lquery::matches('tech.*', 'tech.backend.php'))->toBeTrue();

        expect(Lquery::matches('*.php', 'php'))->toBeTrue();
        expect(Lquery::matches('*.php', 'tech.php'))->toBeTrue();
        expect(Lquery::matches('*.php', 'tech.backend.php'))->toBeTrue();
    });
});

describe('hybrid matching', function (): void {
    it('needsHybridMatch returns false for simple patterns', function (): void {
        expect(Lquery::needsHybridMatch('foo'))->toBeFalse();
        expect(Lquery::needsHybridMatch('foo.bar'))->toBeFalse();
        expect(Lquery::needsHybridMatch('*.foo.*'))->toBeFalse();
    });

    it('needsHybridMatch returns false for single modifiers', function (): void {
        expect(Lquery::needsHybridMatch('foo*'))->toBeFalse();
        expect(Lquery::needsHybridMatch('foo%'))->toBeFalse();
        expect(Lquery::needsHybridMatch('foo@'))->toBeFalse();
    });

    it('needsHybridMatch returns true for prefix + word match', function (): void {
        expect(Lquery::needsHybridMatch('foo*%'))->toBeTrue();
        expect(Lquery::needsHybridMatch('bar.foo*%.baz'))->toBeTrue();
    });

    it('toLooseRegex matches broader set for prefix patterns', function (): void {
        // The loose regex treats % like * (any suffix)
        $looseRegex = Lquery::toLooseRegex('foo%');
        $exactRegex = Lquery::toRegex('foo%');

        // Both should match foo and foo_bar
        expect(preg_match('/'.$looseRegex.'/', 'foo'))->toBe(1);
        expect(preg_match('/'.$looseRegex.'/', 'foo_bar'))->toBe(1);

        // Loose regex also matches foobar (which exact wouldn't)
        expect(preg_match('/'.$looseRegex.'/', 'foobar'))->toBe(1);

        // Exact regex should NOT match foobar
        expect(preg_match('/'.$exactRegex.'/', 'foobar'))->toBe(0);
    });

    it('hybridFilter returns correct results', function (): void {
        $paths = ['foo', 'foo_bar', 'foo_baz', 'foobar', 'bar'];

        $result = Lquery::hybridFilter($paths, 'foo%');

        expect($result->values()->all())->toBe(['foo', 'foo_bar', 'foo_baz']);
    });
});
