<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Lquery\Token;

describe('needsPostFilter', function (): void {
    it('returns false for simple label', function (): void {
        $token = Token::label('foo');
        expect($token->needsPostFilter())->toBeFalse();
    });

    it('returns false for prefix match only', function (): void {
        $token = Token::label('foo', prefixMatch: true);
        expect($token->needsPostFilter())->toBeFalse();
    });

    it('returns false for word match only', function (): void {
        $token = Token::label('foo', wordMatch: true);
        expect($token->needsPostFilter())->toBeFalse();
    });

    it('returns true for prefix + word match combination', function (): void {
        $token = Token::label('foo', prefixMatch: true, wordMatch: true);
        expect($token->needsPostFilter())->toBeTrue();
    });

    it('returns false for star tokens', function (): void {
        $token = Token::star();
        expect($token->needsPostFilter())->toBeFalse();
    });

    it('returns false for simple group', function (): void {
        $token = Token::group([
            ['value' => 'foo', 'caseInsensitive' => false, 'prefixMatch' => false, 'wordMatch' => false],
            ['value' => 'bar', 'caseInsensitive' => false, 'prefixMatch' => false, 'wordMatch' => false],
        ]);
        expect($token->needsPostFilter())->toBeFalse();
    });

    it('returns true for group with prefix + word match alternative', function (): void {
        $token = Token::group([
            ['value' => 'foo', 'caseInsensitive' => false, 'prefixMatch' => true, 'wordMatch' => true],
            ['value' => 'bar', 'caseInsensitive' => false, 'prefixMatch' => false, 'wordMatch' => false],
        ]);
        expect($token->needsPostFilter())->toBeTrue();
    });
});
