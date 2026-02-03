<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\LqueryParseException;
use Birdcar\LabelTree\Query\Lquery\Parser;

describe('LqueryParseException factory methods', function (): void {
    it('creates emptyPattern exception', function (): void {
        $e = LqueryParseException::emptyPattern();

        expect($e)->toBeInstanceOf(LqueryParseException::class);
        expect($e)->toBeInstanceOf(InvalidArgumentException::class);
        expect($e->getMessage())->toBe('Empty lquery pattern');
    });

    it('creates emptyElement exception', function (): void {
        $e = LqueryParseException::emptyElement();

        expect($e)->toBeInstanceOf(LqueryParseException::class);
        expect($e->getMessage())->toBe('Empty element in lquery pattern');
    });

    it('creates invalidQuantifier exception', function (): void {
        $e = LqueryParseException::invalidQuantifier('{abc}');

        expect($e)->toBeInstanceOf(LqueryParseException::class);
        expect($e->getMessage())->toBe('Invalid quantifier: {abc}');
    });

    it('creates invalidLabel exception', function (): void {
        $e = LqueryParseException::invalidLabel('foo/bar');

        expect($e)->toBeInstanceOf(LqueryParseException::class);
        expect($e->getMessage())->toBe('Invalid label characters: foo/bar');
    });
});

describe('Parser throws LqueryParseException', function (): void {
    beforeEach(function (): void {
        $this->parser = new Parser;
    });

    it('throws LqueryParseException on empty pattern', function (): void {
        $this->parser->parse('');
    })->throws(LqueryParseException::class, 'Empty lquery pattern');

    it('throws LqueryParseException on empty element', function (): void {
        $this->parser->parse('foo..bar');
    })->throws(LqueryParseException::class, 'Empty element');

    it('throws LqueryParseException on invalid characters', function (): void {
        $this->parser->parse('foo/bar');
    })->throws(LqueryParseException::class, 'Invalid label characters');

    it('throws LqueryParseException on invalid quantifier', function (): void {
        $this->parser->parse('*{abc}');
    })->throws(LqueryParseException::class, 'Invalid quantifier');
});
