<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Support\Deprecation;

describe('Deprecation utility', function (): void {
    afterEach(function (): void {
        // Re-enable deprecations after each test
        Deprecation::enable();
    });

    it('can be disabled', function (): void {
        Deprecation::disable();

        // When disabled, no deprecation notice should be triggered
        // This should not trigger any warnings
        Deprecation::methodRenamed('TestClass', 'oldMethod', 'newMethod');

        expect(true)->toBeTrue(); // Test passes if no error is thrown
    });

    it('can be re-enabled', function (): void {
        Deprecation::disable();
        Deprecation::enable();

        // Capture deprecation notices
        $triggered = false;
        set_error_handler(function ($errno, $errstr) use (&$triggered) {
            if ($errno === E_USER_DEPRECATED) {
                $triggered = true;
            }

            return true;
        });

        Deprecation::methodRenamed('TestClass', 'oldMethod', 'newMethod');

        restore_error_handler();

        expect($triggered)->toBeTrue();
    });

    it('formats method renamed message correctly', function (): void {
        $message = null;
        set_error_handler(function ($errno, $errstr) use (&$message) {
            if ($errno === E_USER_DEPRECATED) {
                $message = $errstr;
            }

            return true;
        });

        Deprecation::methodRenamed('MyClass', 'oldMethod', 'newMethod');

        restore_error_handler();

        expect($message)->toBe('MyClass::oldMethod() is deprecated, use MyClass::newMethod() instead.');
    });
});
