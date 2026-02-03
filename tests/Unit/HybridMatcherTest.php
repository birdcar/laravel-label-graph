<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Lquery\HybridMatcher;
use Birdcar\LabelTree\Query\Lquery\Lquery;

describe('HybridMatcher::needsHybrid', function (): void {
    it('returns false for simple pattern', function (): void {
        $tokens = Lquery::parse('foo.bar');
        expect(HybridMatcher::needsHybrid($tokens))->toBeFalse();
    });

    it('returns false for pattern with star', function (): void {
        $tokens = Lquery::parse('*.foo.*');
        expect(HybridMatcher::needsHybrid($tokens))->toBeFalse();
    });

    it('returns false for prefix match only', function (): void {
        $tokens = Lquery::parse('foo*');
        expect(HybridMatcher::needsHybrid($tokens))->toBeFalse();
    });

    it('returns false for word match only', function (): void {
        $tokens = Lquery::parse('foo%');
        expect(HybridMatcher::needsHybrid($tokens))->toBeFalse();
    });

    it('returns true for prefix + word match combination', function (): void {
        $tokens = Lquery::parse('foo*%');
        expect(HybridMatcher::needsHybrid($tokens))->toBeTrue();
    });

    it('returns true when any token needs post filter', function (): void {
        $tokens = Lquery::parse('bar.foo*%.baz');
        expect(HybridMatcher::needsHybrid($tokens))->toBeTrue();
    });
});

describe('HybridMatcher::filter', function (): void {
    it('filters paths by pattern', function (): void {
        $matcher = new HybridMatcher;
        $paths = ['foo', 'bar', 'baz', 'foo.bar', 'qux'];

        $result = $matcher->filter($paths, 'foo');

        expect($result->values()->all())->toBe(['foo']);
    });

    it('filters with wildcard patterns', function (): void {
        $matcher = new HybridMatcher;
        $paths = ['foo', 'foo.bar', 'foo.bar.baz', 'bar.foo'];

        $result = $matcher->filter($paths, 'foo.*');

        expect($result->values()->all())->toBe(['foo', 'foo.bar', 'foo.bar.baz']);
    });

    it('filters with prefix match', function (): void {
        $matcher = new HybridMatcher;
        $paths = ['foo', 'foobar', 'food', 'bar'];

        $result = $matcher->filter($paths, 'foo*');

        expect($result->values()->all())->toBe(['foo', 'foobar', 'food']);
    });

    it('filters with word match', function (): void {
        $matcher = new HybridMatcher;
        $paths = ['foo', 'foo_bar', 'foo_bar_baz', 'foobar'];

        $result = $matcher->filter($paths, 'foo%');

        expect($result->values()->all())->toBe(['foo', 'foo_bar', 'foo_bar_baz']);
    });

    it('accepts collections', function (): void {
        $matcher = new HybridMatcher;
        $paths = collect(['foo', 'bar', 'baz']);

        $result = $matcher->filter($paths, 'foo|bar');

        expect($result->values()->all())->toBe(['foo', 'bar']);
    });
});
