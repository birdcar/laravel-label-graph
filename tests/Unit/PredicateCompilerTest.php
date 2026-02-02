<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Ltxtquery\Ltxtquery;
use Birdcar\LabelTree\Query\Ltxtquery\PredicateCompiler;
use Birdcar\LabelTree\Query\Ltxtquery\Token;

describe('PredicateCompiler word matching', function (): void {
    it('matches exact word in path', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(Token::word('Europe'));

        expect($predicate('Europe'))->toBeTrue();
        expect($predicate('Europe.France'))->toBeTrue();
        expect($predicate('World.Europe.France'))->toBeTrue();
        expect($predicate('Asia'))->toBeFalse();
    });

    it('matches prefix with modifier', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(Token::word('Russia', prefixMatch: true));

        expect($predicate('Russia'))->toBeTrue();
        expect($predicate('Russian'))->toBeTrue();
        expect($predicate('RussianFederation'))->toBeTrue();
        expect($predicate('Europe.Russian'))->toBeTrue();
        expect($predicate('Belarussia'))->toBeFalse();
    });

    it('matches case-insensitive with modifier', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(Token::word('russia', caseInsensitive: true));

        expect($predicate('russia'))->toBeTrue();
        expect($predicate('Russia'))->toBeTrue();
        expect($predicate('RUSSIA'))->toBeTrue();
        expect($predicate('RuSsIa'))->toBeTrue();
        expect($predicate('France'))->toBeFalse();
    });

    it('matches word-boundary with modifier', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(Token::word('foo_bar', wordMatch: true));

        expect($predicate('foo_bar'))->toBeTrue();
        expect($predicate('foo_bar_baz'))->toBeTrue();
        expect($predicate('prefix.foo_bar_baz.suffix'))->toBeTrue();
        expect($predicate('foo_barbaz'))->toBeFalse();
        expect($predicate('foobar'))->toBeFalse();
    });

    it('matches combined prefix and word-boundary', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(Token::word('foo', prefixMatch: true, wordMatch: true));

        expect($predicate('foo'))->toBeTrue();
        expect($predicate('foobar'))->toBeTrue();
        expect($predicate('foo_bar'))->toBeTrue();
        expect($predicate('foobar_baz'))->toBeTrue();
        expect($predicate('bar_foo'))->toBeFalse();
    });
});

describe('PredicateCompiler boolean operations', function (): void {
    it('handles AND operation', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(
            Token::and(Token::word('Europe'), Token::word('France'))
        );

        expect($predicate('Europe.France'))->toBeTrue();
        expect($predicate('France.Europe'))->toBeTrue();
        expect($predicate('World.Europe.France.Paris'))->toBeTrue();
        expect($predicate('Europe'))->toBeFalse();
        expect($predicate('France'))->toBeFalse();
    });

    it('handles OR operation', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(
            Token::or(Token::word('Europe'), Token::word('Asia'))
        );

        expect($predicate('Europe'))->toBeTrue();
        expect($predicate('Asia'))->toBeTrue();
        expect($predicate('World.Europe'))->toBeTrue();
        expect($predicate('World.Asia'))->toBeTrue();
        expect($predicate('Africa'))->toBeFalse();
    });

    it('handles NOT operation', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(
            Token::not(Token::word('Europe'))
        );

        expect($predicate('Asia'))->toBeTrue();
        expect($predicate('Africa.Nigeria'))->toBeTrue();
        expect($predicate('Europe'))->toBeFalse();
        expect($predicate('World.Europe.France'))->toBeFalse();
    });

    it('handles GROUP operation', function (): void {
        $compiler = new PredicateCompiler;
        $predicate = $compiler->compile(
            Token::group(Token::word('Europe'))
        );

        expect($predicate('Europe'))->toBeTrue();
        expect($predicate('Asia'))->toBeFalse();
    });

    it('handles complex nested expression', function (): void {
        $compiler = new PredicateCompiler;
        // (Europe | Asia) & !Africa
        $predicate = $compiler->compile(
            Token::and(
                Token::group(Token::or(Token::word('Europe'), Token::word('Asia'))),
                Token::not(Token::word('Africa'))
            )
        );

        expect($predicate('Europe'))->toBeTrue();
        expect($predicate('Asia'))->toBeTrue();
        expect($predicate('Europe.France'))->toBeTrue();
        expect($predicate('Europe.Africa'))->toBeFalse(); // Has Africa
        expect($predicate('Africa'))->toBeFalse();
        expect($predicate('America'))->toBeFalse(); // No Europe or Asia
    });
});

describe('Ltxtquery::matches convenience method', function (): void {
    it('matches simple word', function (): void {
        expect(Ltxtquery::matches('Europe', 'Europe'))->toBeTrue();
        expect(Ltxtquery::matches('Europe', 'Europe.France'))->toBeTrue();
        expect(Ltxtquery::matches('Europe', 'Asia'))->toBeFalse();
    });

    it('matches AND expression', function (): void {
        expect(Ltxtquery::matches('Europe & France', 'Europe.France'))->toBeTrue();
        expect(Ltxtquery::matches('Europe & France', 'Europe'))->toBeFalse();
    });

    it('matches OR expression', function (): void {
        expect(Ltxtquery::matches('Europe | Asia', 'Europe'))->toBeTrue();
        expect(Ltxtquery::matches('Europe | Asia', 'Asia'))->toBeTrue();
        expect(Ltxtquery::matches('Europe | Asia', 'Africa'))->toBeFalse();
    });

    it('matches NOT expression', function (): void {
        expect(Ltxtquery::matches('!Europe', 'Asia'))->toBeTrue();
        expect(Ltxtquery::matches('!Europe', 'Europe'))->toBeFalse();
    });

    it('matches with modifiers', function (): void {
        expect(Ltxtquery::matches('Russia*', 'Russian'))->toBeTrue();
        expect(Ltxtquery::matches('russia@', 'RUSSIA'))->toBeTrue();
        expect(Ltxtquery::matches('foo_bar%', 'foo_bar_baz'))->toBeTrue();
    });
});

describe('Ltxtquery::filter convenience method', function (): void {
    it('filters paths by pattern', function (): void {
        $paths = ['Europe', 'Europe.France', 'Asia', 'Asia.Japan', 'Africa'];

        $result = Ltxtquery::filter($paths, 'Europe');

        expect($result->all())->toBe(['Europe', 'Europe.France']);
    });

    it('filters with boolean expression', function (): void {
        $paths = ['Europe.France', 'Europe.Germany', 'Asia.Japan', 'Africa.Egypt'];

        $result = Ltxtquery::filter($paths, 'Europe | Asia');

        expect($result->all())->toBe(['Europe.France', 'Europe.Germany', 'Asia.Japan']);
    });

    it('filters with NOT expression', function (): void {
        $paths = ['Europe', 'Asia', 'Africa'];

        $result = Ltxtquery::filter($paths, '!Europe');

        expect($result->all())->toBe(['Asia', 'Africa']);
    });
});
