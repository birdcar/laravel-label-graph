<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Ltxtquery\Ltxtquery;
use Birdcar\LabelTree\Query\Ltxtquery\LtxtqueryCompiler;
use Birdcar\LabelTree\Query\Ltxtquery\Token;

describe('LtxtqueryCompiler compiles to native syntax', function (): void {
    it('compiles single word', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::word('Europe');

        expect($compiler->compile($ast))->toBe('Europe');
    });

    it('compiles word with prefix modifier', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::word('Russia', prefixMatch: true);

        expect($compiler->compile($ast))->toBe('Russia*');
    });

    it('compiles word with case-insensitive modifier', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::word('russia', caseInsensitive: true);

        expect($compiler->compile($ast))->toBe('russia@');
    });

    it('compiles word with word-boundary modifier', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::word('foo_bar', wordMatch: true);

        expect($compiler->compile($ast))->toBe('foo_bar%');
    });

    it('compiles word with multiple modifiers', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::word('Russia', caseInsensitive: true, prefixMatch: true, wordMatch: true);

        expect($compiler->compile($ast))->toBe('Russia*@%');
    });

    it('compiles AND expression', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::and(Token::word('Europe'), Token::word('Asia'));

        expect($compiler->compile($ast))->toBe('Europe & Asia');
    });

    it('compiles OR expression', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::or(Token::word('Europe'), Token::word('Asia'));

        expect($compiler->compile($ast))->toBe('Europe | Asia');
    });

    it('compiles NOT expression', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::not(Token::word('Europe'));

        expect($compiler->compile($ast))->toBe('!Europe');
    });

    it('compiles GROUP expression', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::group(Token::word('Europe'));

        expect($compiler->compile($ast))->toBe('(Europe)');
    });

    it('compiles complex nested expression', function (): void {
        $compiler = new LtxtqueryCompiler;
        $ast = Token::and(
            Token::group(Token::or(Token::word('Europe'), Token::word('Asia'))),
            Token::not(Token::word('Africa'))
        );

        expect($compiler->compile($ast))->toBe('(Europe | Asia) & !Africa');
    });
});

describe('Ltxtquery::toNative round-trip', function (): void {
    it('round-trips simple word', function (): void {
        expect(Ltxtquery::toNative('Europe'))->toBe('Europe');
    });

    it('round-trips AND expression', function (): void {
        expect(Ltxtquery::toNative('Europe & Asia'))->toBe('Europe & Asia');
    });

    it('round-trips OR expression', function (): void {
        expect(Ltxtquery::toNative('Europe | Asia'))->toBe('Europe | Asia');
    });

    it('round-trips NOT expression', function (): void {
        expect(Ltxtquery::toNative('!Europe'))->toBe('!Europe');
    });

    it('round-trips complex expression', function (): void {
        expect(Ltxtquery::toNative('(Europe | Asia) & !Africa'))->toBe('(Europe | Asia) & !Africa');
    });

    it('round-trips word with modifiers', function (): void {
        expect(Ltxtquery::toNative('Russia*@'))->toBe('Russia*@');
    });
});
