<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\LtxtqueryParseException;
use Birdcar\LabelTree\Query\Ltxtquery\Parser;
use Birdcar\LabelTree\Query\Ltxtquery\Token;

describe('LtxtqueryParser basic parsing', function (): void {
    it('parses single word', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Europe');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('Europe');
    });

    it('parses word with underscores', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('foo_bar');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('foo_bar');
    });

    it('parses word with hyphens', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('foo-bar');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('foo-bar');
    });

    it('parses word with numbers', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('foo123');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('foo123');
    });
});

describe('LtxtqueryParser modifiers', function (): void {
    it('parses case-insensitive modifier', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Europe@');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('Europe');
        expect($ast->caseInsensitive)->toBeTrue();
    });

    it('parses prefix modifier', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Russia*');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('Russia');
        expect($ast->prefixMatch)->toBeTrue();
    });

    it('parses word-boundary modifier', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('foo_bar%');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('foo_bar');
        expect($ast->wordMatch)->toBeTrue();
    });

    it('parses multiple modifiers', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Russia*@%');

        expect($ast->type)->toBe(Token::TYPE_WORD);
        expect($ast->value)->toBe('Russia');
        expect($ast->caseInsensitive)->toBeTrue();
        expect($ast->prefixMatch)->toBeTrue();
        expect($ast->wordMatch)->toBeTrue();
    });
});

describe('LtxtqueryParser boolean operators', function (): void {
    it('parses AND expression', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Europe & Asia');

        expect($ast->type)->toBe(Token::TYPE_AND);
        expect($ast->children[0]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[0]->value)->toBe('Europe');
        expect($ast->children[1]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[1]->value)->toBe('Asia');
    });

    it('parses OR expression', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Europe | Asia');

        expect($ast->type)->toBe(Token::TYPE_OR);
        expect($ast->children[0]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[0]->value)->toBe('Europe');
        expect($ast->children[1]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[1]->value)->toBe('Asia');
    });

    it('parses NOT expression', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('!Europe');

        expect($ast->type)->toBe(Token::TYPE_NOT);
        expect($ast->children[0]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[0]->value)->toBe('Europe');
    });

    it('handles AND before OR precedence', function (): void {
        $parser = new Parser;
        // A | B & C should parse as A | (B & C)
        $ast = $parser->parse('A | B & C');

        expect($ast->type)->toBe(Token::TYPE_OR);
        expect($ast->children[0]->value)->toBe('A');
        expect($ast->children[1]->type)->toBe(Token::TYPE_AND);
    });
});

describe('LtxtqueryParser grouping', function (): void {
    it('parses parenthesized expression', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('(Europe)');

        expect($ast->type)->toBe(Token::TYPE_GROUP);
        expect($ast->children[0]->type)->toBe(Token::TYPE_WORD);
        expect($ast->children[0]->value)->toBe('Europe');
    });

    it('parses nested boolean with parentheses', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('(Europe | Asia) & !Africa');

        expect($ast->type)->toBe(Token::TYPE_AND);
        expect($ast->children[0]->type)->toBe(Token::TYPE_GROUP);
        expect($ast->children[0]->children[0]->type)->toBe(Token::TYPE_OR);
        expect($ast->children[1]->type)->toBe(Token::TYPE_NOT);
    });
});

describe('LtxtqueryParser whitespace handling', function (): void {
    it('handles whitespace around operators', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('  Europe   &   Asia  ');

        expect($ast->type)->toBe(Token::TYPE_AND);
        expect($ast->children[0]->value)->toBe('Europe');
        expect($ast->children[1]->value)->toBe('Asia');
    });

    it('handles no whitespace around operators', function (): void {
        $parser = new Parser;
        $ast = $parser->parse('Europe&Asia');

        expect($ast->type)->toBe(Token::TYPE_AND);
        expect($ast->children[0]->value)->toBe('Europe');
        expect($ast->children[1]->value)->toBe('Asia');
    });
});

describe('LtxtqueryParser error handling', function (): void {
    it('throws on empty pattern', function (): void {
        $parser = new Parser;

        expect(fn () => $parser->parse(''))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on missing close paren', function (): void {
        $parser = new Parser;

        expect(fn () => $parser->parse('(Europe'))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on unexpected character', function (): void {
        $parser = new Parser;

        expect(fn () => $parser->parse('Europe $'))->toThrow(LtxtqueryParseException::class);
    });

    it('throws on empty expression after operator', function (): void {
        $parser = new Parser;

        expect(fn () => $parser->parse('Europe &'))->toThrow(LtxtqueryParseException::class);
    });
});
