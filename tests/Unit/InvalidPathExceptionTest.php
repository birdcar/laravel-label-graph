<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Exceptions\InvalidPathException;

describe('InvalidPathException factory methods', function (): void {
    it('creates consecutiveDots exception', function (): void {
        $exception = InvalidPathException::consecutiveDots('invalid..path');

        expect($exception)->toBeInstanceOf(InvalidPathException::class);
        expect($exception->getMessage())->toContain('consecutive dots');
        expect($exception->getMessage())->toContain('invalid..path');
    });

    it('creates invalidBoundary exception', function (): void {
        $exception = InvalidPathException::invalidBoundary('.invalid');

        expect($exception)->toBeInstanceOf(InvalidPathException::class);
        expect($exception->getMessage())->toContain('start or end');
        expect($exception->getMessage())->toContain('.invalid');
    });

    it('creates invalidLabel exception', function (): void {
        $exception = InvalidPathException::invalidLabel('bad!label', 'path.bad!label');

        expect($exception)->toBeInstanceOf(InvalidPathException::class);
        expect($exception->getMessage())->toContain('bad!label');
        expect($exception->getMessage())->toContain('alphanumeric');
    });

    it('extends InvalidArgumentException', function (): void {
        $exception = InvalidPathException::consecutiveDots('test');

        expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    });
});
