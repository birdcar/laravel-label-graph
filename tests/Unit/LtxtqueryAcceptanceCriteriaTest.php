<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\LtxtqueryParseException;
use Birdcar\LabelGraph\Query\Ltxtquery\Ltxtquery;

/**
 * Tests for PRD Phase 2 Acceptance Criteria.
 */
describe('Acceptance: Europe matches paths containing label Europe anywhere', function (): void {
    it('matches path starting with Europe', function (): void {
        expect(Ltxtquery::matches('Europe', 'Europe'))->toBeTrue();
    });

    it('matches path containing Europe in middle', function (): void {
        expect(Ltxtquery::matches('Europe', 'World.Europe.France'))->toBeTrue();
    });

    it('matches path ending with Europe', function (): void {
        expect(Ltxtquery::matches('Europe', 'World.Europe'))->toBeTrue();
    });

    it('does not match path without Europe', function (): void {
        expect(Ltxtquery::matches('Europe', 'Asia.Japan'))->toBeFalse();
    });
});

describe('Acceptance: Europe & Asia matches paths containing both labels', function (): void {
    it('matches when both present', function (): void {
        expect(Ltxtquery::matches('Europe & Asia', 'Europe.Asia'))->toBeTrue();
    });

    it('matches regardless of order', function (): void {
        expect(Ltxtquery::matches('Europe & Asia', 'Asia.Europe'))->toBeTrue();
    });

    it('matches when both present in longer path', function (): void {
        expect(Ltxtquery::matches('Europe & Asia', 'World.Europe.Trade.Asia'))->toBeTrue();
    });

    it('does not match with only Europe', function (): void {
        expect(Ltxtquery::matches('Europe & Asia', 'Europe.France'))->toBeFalse();
    });

    it('does not match with only Asia', function (): void {
        expect(Ltxtquery::matches('Europe & Asia', 'Asia.Japan'))->toBeFalse();
    });
});

describe('Acceptance: Europe | Asia matches paths containing either label', function (): void {
    it('matches with Europe only', function (): void {
        expect(Ltxtquery::matches('Europe | Asia', 'Europe'))->toBeTrue();
    });

    it('matches with Asia only', function (): void {
        expect(Ltxtquery::matches('Europe | Asia', 'Asia'))->toBeTrue();
    });

    it('matches with both present', function (): void {
        expect(Ltxtquery::matches('Europe | Asia', 'Europe.Asia'))->toBeTrue();
    });

    it('does not match with neither', function (): void {
        expect(Ltxtquery::matches('Europe | Asia', 'Africa'))->toBeFalse();
    });
});

describe('Acceptance: !Europe matches paths NOT containing Europe', function (): void {
    it('matches path without Europe', function (): void {
        expect(Ltxtquery::matches('!Europe', 'Asia'))->toBeTrue();
    });

    it('matches path without Europe in complex path', function (): void {
        expect(Ltxtquery::matches('!Europe', 'Asia.Japan.Tokyo'))->toBeTrue();
    });

    it('does not match path with Europe', function (): void {
        expect(Ltxtquery::matches('!Europe', 'Europe'))->toBeFalse();
    });

    it('does not match path containing Europe', function (): void {
        expect(Ltxtquery::matches('!Europe', 'World.Europe.France'))->toBeFalse();
    });
});

describe('Acceptance: (Europe | Asia) & !Africa parses and matches correctly', function (): void {
    it('matches Europe without Africa', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Europe'))->toBeTrue();
    });

    it('matches Asia without Africa', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Asia'))->toBeTrue();
    });

    it('matches Europe.France without Africa', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Europe.France'))->toBeTrue();
    });

    it('does not match Europe with Africa', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Europe.Africa'))->toBeFalse();
    });

    it('does not match Africa alone', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Africa'))->toBeFalse();
    });

    it('does not match path with only Africa', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'Africa.Nigeria'))->toBeFalse();
    });

    it('does not match path without Europe or Asia', function (): void {
        expect(Ltxtquery::matches('(Europe | Asia) & !Africa', 'America'))->toBeFalse();
    });
});

describe('Acceptance: Russia* matches Russia, Russian, RussianFederation', function (): void {
    it('matches exact Russia', function (): void {
        expect(Ltxtquery::matches('Russia*', 'Russia'))->toBeTrue();
    });

    it('matches Russian', function (): void {
        expect(Ltxtquery::matches('Russia*', 'Russian'))->toBeTrue();
    });

    it('matches RussianFederation', function (): void {
        expect(Ltxtquery::matches('Russia*', 'RussianFederation'))->toBeTrue();
    });

    it('matches in complex path', function (): void {
        expect(Ltxtquery::matches('Russia*', 'World.RussianFederation.Moscow'))->toBeTrue();
    });

    it('does not match Belarussia', function (): void {
        expect(Ltxtquery::matches('Russia*', 'Belarussia'))->toBeFalse();
    });
});

describe('Acceptance: russia@ matches Russia, RUSSIA, russia case-insensitive', function (): void {
    it('matches lowercase russia', function (): void {
        expect(Ltxtquery::matches('russia@', 'russia'))->toBeTrue();
    });

    it('matches capitalized Russia', function (): void {
        expect(Ltxtquery::matches('russia@', 'Russia'))->toBeTrue();
    });

    it('matches uppercase RUSSIA', function (): void {
        expect(Ltxtquery::matches('russia@', 'RUSSIA'))->toBeTrue();
    });

    it('matches mixed case RuSsIa', function (): void {
        expect(Ltxtquery::matches('russia@', 'RuSsIa'))->toBeTrue();
    });

    it('does not match partial without case-insensitive prefix', function (): void {
        expect(Ltxtquery::matches('russia@', 'Russians'))->toBeFalse();
    });
});

describe('Acceptance: foo_bar% matches paths with foo_bar, foo_bar_baz', function (): void {
    it('matches exact foo_bar', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'foo_bar'))->toBeTrue();
    });

    it('matches foo_bar_baz', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'foo_bar_baz'))->toBeTrue();
    });

    it('matches foo_bar_baz_qux', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'foo_bar_baz_qux'))->toBeTrue();
    });

    it('matches in complex path', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'prefix.foo_bar_baz.suffix'))->toBeTrue();
    });

    it('does not match foo_barbaz without underscore', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'foo_barbaz'))->toBeFalse();
    });

    it('does not match foobar without first underscore', function (): void {
        expect(Ltxtquery::matches('foo_bar%', 'foobar'))->toBeFalse();
    });
});

describe('Acceptance: Whitespace is allowed', function (): void {
    it('parses Europe & Russia with spaces', function (): void {
        expect(Ltxtquery::validate('Europe & Russia'))->toBeTrue();
    });

    it('matches with spaces around operators', function (): void {
        expect(Ltxtquery::matches('Europe & Russia', 'Europe.Russia'))->toBeTrue();
    });

    it('handles extra whitespace', function (): void {
        expect(Ltxtquery::matches('  Europe   &   Russia  ', 'Europe.Russia'))->toBeTrue();
    });

    it('handles no whitespace', function (): void {
        expect(Ltxtquery::matches('Europe&Russia', 'Europe.Russia'))->toBeTrue();
    });
});

describe('Acceptance: Invalid syntax throws LtxtqueryParseException', function (): void {
    it('throws on empty pattern', function (): void {
        expect(fn () => Ltxtquery::parse(''))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on missing close paren', function (): void {
        expect(fn () => Ltxtquery::parse('(Europe'))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on unexpected character', function (): void {
        expect(fn () => Ltxtquery::parse('Europe $'))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on incomplete expression', function (): void {
        expect(fn () => Ltxtquery::parse('Europe &'))->toThrow(LtxtqueryParseException::class);
    });
});

describe('Acceptance: Static helpers work without database connection', function (): void {
    it('parse returns AST', function (): void {
        $ast = Ltxtquery::parse('Europe & Asia');
        expect($ast->type)->toBe('and');
    });

    it('validate returns true for valid', function (): void {
        expect(Ltxtquery::validate('Europe & Asia'))->toBeTrue();
    });

    it('validate returns false for invalid', function (): void {
        expect(Ltxtquery::validate('(incomplete'))->toBeFalse();
    });

    it('toNative returns string', function (): void {
        expect(Ltxtquery::toNative('Europe & Asia'))->toBe('Europe & Asia');
    });

    it('matches works standalone', function (): void {
        expect(Ltxtquery::matches('Europe', 'Europe'))->toBeTrue();
    });

    it('filter works standalone', function (): void {
        $result = Ltxtquery::filter(['Europe', 'Asia'], 'Europe');
        expect($result->all())->toBe(['Europe']);
    });
});

describe('Acceptance: PostgreSQL compiles to native ltxtquery', function (): void {
    it('compiles simple word', function (): void {
        expect(Ltxtquery::toNative('Europe'))->toBe('Europe');
    });

    it('compiles AND expression', function (): void {
        expect(Ltxtquery::toNative('Europe & Asia'))->toBe('Europe & Asia');
    });

    it('compiles OR expression', function (): void {
        expect(Ltxtquery::toNative('Europe | Asia'))->toBe('Europe | Asia');
    });

    it('compiles NOT expression', function (): void {
        expect(Ltxtquery::toNative('!Europe'))->toBe('!Europe');
    });

    it('compiles complex expression', function (): void {
        expect(Ltxtquery::toNative('(Europe | Asia) & !Africa'))->toBe('(Europe | Asia) & !Africa');
    });

    it('compiles with modifiers', function (): void {
        expect(Ltxtquery::toNative('Russia*@'))->toBe('Russia*@');
    });
});
