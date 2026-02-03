<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\LtxtqueryParseException;

describe('LtxtqueryParseException factory methods', function (): void {
    it('creates emptyPattern exception', function (): void {
        $exception = LtxtqueryParseException::emptyPattern();

        expect($exception)->toBeInstanceOf(LtxtqueryParseException::class);
        expect($exception->getMessage())->toContain('Empty');
    });

    it('creates unexpectedChar exception', function (): void {
        $exception = LtxtqueryParseException::unexpectedChar('$', 5);

        expect($exception)->toBeInstanceOf(LtxtqueryParseException::class);
        expect($exception->getMessage())->toContain('$');
        expect($exception->getMessage())->toContain('5');
    });

    it('creates missingCloseParen exception', function (): void {
        $exception = LtxtqueryParseException::missingCloseParen(10);

        expect($exception)->toBeInstanceOf(LtxtqueryParseException::class);
        expect($exception->getMessage())->toContain('parenthesis');
        expect($exception->getMessage())->toContain('10');
    });

    it('creates expectedWord exception', function (): void {
        $exception = LtxtqueryParseException::expectedWord(3);

        expect($exception)->toBeInstanceOf(LtxtqueryParseException::class);
        expect($exception->getMessage())->toContain('word');
        expect($exception->getMessage())->toContain('3');
    });

    it('extends InvalidArgumentException', function (): void {
        $exception = LtxtqueryParseException::emptyPattern();

        expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    });
});
