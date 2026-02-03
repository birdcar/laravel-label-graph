<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Ltxtquery\Token;

describe('Token factory methods', function (): void {
    it('creates word token', function (): void {
        $token = Token::word('foo');

        expect($token->type)->toBe(Token::TYPE_WORD);
        expect($token->value)->toBe('foo');
        expect($token->caseInsensitive)->toBeFalse();
        expect($token->prefixMatch)->toBeFalse();
        expect($token->wordMatch)->toBeFalse();
        expect($token->children)->toBe([]);
    });

    it('creates word token with modifiers', function (): void {
        $token = Token::word('bar', caseInsensitive: true, prefixMatch: true, wordMatch: true);

        expect($token->type)->toBe(Token::TYPE_WORD);
        expect($token->value)->toBe('bar');
        expect($token->caseInsensitive)->toBeTrue();
        expect($token->prefixMatch)->toBeTrue();
        expect($token->wordMatch)->toBeTrue();
    });

    it('creates AND token', function (): void {
        $left = Token::word('foo');
        $right = Token::word('bar');
        $token = Token::and($left, $right);

        expect($token->type)->toBe(Token::TYPE_AND);
        expect($token->children)->toBe([$left, $right]);
    });

    it('creates OR token', function (): void {
        $left = Token::word('foo');
        $right = Token::word('bar');
        $token = Token::or($left, $right);

        expect($token->type)->toBe(Token::TYPE_OR);
        expect($token->children)->toBe([$left, $right]);
    });

    it('creates NOT token', function (): void {
        $operand = Token::word('foo');
        $token = Token::not($operand);

        expect($token->type)->toBe(Token::TYPE_NOT);
        expect($token->children)->toBe([$operand]);
    });

    it('creates GROUP token', function (): void {
        $inner = Token::word('foo');
        $token = Token::group($inner);

        expect($token->type)->toBe(Token::TYPE_GROUP);
        expect($token->children)->toBe([$inner]);
    });
});
