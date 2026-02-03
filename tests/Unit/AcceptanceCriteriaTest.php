<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Lquery\Lquery;
use Birdcar\LabelTree\Support\Deprecation;

/**
 * Tests for PRD Phase 1 Acceptance Criteria.
 */
describe('Acceptance Criteria: *{0} matches empty segments correctly', function (): void {
    it('*{0} at start allows pattern to start at next element', function (): void {
        // *{0}.foo should match just "foo" (zero labels before foo)
        expect(Lquery::matches('*{0}.foo', 'foo'))->toBeTrue();
    });

    it('*{0} between elements allows adjacent elements', function (): void {
        // foo.*{0}.bar should match "foo.bar" (zero labels between)
        expect(Lquery::matches('foo.*{0}.bar', 'foo.bar'))->toBeTrue();
    });

    it('*{0} does not match when there are labels', function (): void {
        // foo.*{0}.bar should NOT match "foo.x.bar" (one label between)
        expect(Lquery::matches('foo.*{0}.bar', 'foo.x.bar'))->toBeFalse();
    });
});

describe('Acceptance Criteria: *{2,5} matches exactly 2-5 labels', function (): void {
    it('fails with 1 label', function (): void {
        expect(Lquery::matches('*{2,5}', 'a'))->toBeFalse();
    });

    it('matches with 2 labels', function (): void {
        expect(Lquery::matches('*{2,5}', 'a.b'))->toBeTrue();
    });

    it('matches with 3 labels', function (): void {
        expect(Lquery::matches('*{2,5}', 'a.b.c'))->toBeTrue();
    });

    it('matches with 4 labels', function (): void {
        expect(Lquery::matches('*{2,5}', 'a.b.c.d'))->toBeTrue();
    });

    it('matches with 5 labels', function (): void {
        expect(Lquery::matches('*{2,5}', 'a.b.c.d.e'))->toBeTrue();
    });

    it('fails with 6 labels', function (): void {
        expect(Lquery::matches('*{2,5}', 'a.b.c.d.e.f'))->toBeFalse();
    });
});

describe('Acceptance Criteria: label quantifier foo{,}', function (): void {
    it('matches zero occurrences (just foo{,})', function (): void {
        // This is tricky - foo{,} alone would need to match empty string
        // More practically, test with context: bar.foo{,} should match "bar"
        expect(Lquery::matches('bar.foo{,}', 'bar'))->toBeTrue();
    });

    it('matches one occurrence', function (): void {
        expect(Lquery::matches('bar.foo{,}', 'bar.foo'))->toBeTrue();
    });

    it('matches two occurrences', function (): void {
        expect(Lquery::matches('bar.foo{,}', 'bar.foo.foo'))->toBeTrue();
    });

    it('matches three occurrences', function (): void {
        expect(Lquery::matches('bar.foo{,}', 'bar.foo.foo.foo'))->toBeTrue();
    });

    it('does not match different label', function (): void {
        expect(Lquery::matches('bar.foo{,}', 'bar.baz'))->toBeFalse();
    });
});

describe('Acceptance Criteria: FOO@ case-insensitive matching', function (): void {
    it('matches lowercase', function (): void {
        expect(Lquery::matches('foo@', 'foo'))->toBeTrue();
    });

    it('matches uppercase', function (): void {
        expect(Lquery::matches('foo@', 'FOO'))->toBeTrue();
    });

    it('matches mixed case', function (): void {
        expect(Lquery::matches('foo@', 'Foo'))->toBeTrue();
    });

    it('matches FoO mixed case', function (): void {
        expect(Lquery::matches('foo@', 'FoO'))->toBeTrue();
    });

    it('without @ does not match different case', function (): void {
        expect(Lquery::matches('foo', 'FOO'))->toBeFalse();
    });
});

describe('Acceptance Criteria: foo* prefix matching', function (): void {
    it('matches exact', function (): void {
        expect(Lquery::matches('foo*', 'foo'))->toBeTrue();
    });

    it('matches foobar', function (): void {
        expect(Lquery::matches('foo*', 'foobar'))->toBeTrue();
    });

    it('matches foo123', function (): void {
        expect(Lquery::matches('foo*', 'foo123'))->toBeTrue();
    });

    it('matches fooBAR mixed case', function (): void {
        expect(Lquery::matches('foo*', 'fooBAR'))->toBeTrue();
    });

    it('does not match barfoo', function (): void {
        expect(Lquery::matches('foo*', 'barfoo'))->toBeFalse();
    });

    it('does not match bar', function (): void {
        expect(Lquery::matches('foo*', 'bar'))->toBeFalse();
    });
});

describe('Acceptance Criteria: foo_bar% word boundary matching', function (): void {
    it('matches exact foo_bar', function (): void {
        expect(Lquery::matches('foo_bar%', 'foo_bar'))->toBeTrue();
    });

    it('matches foo_bar_baz', function (): void {
        expect(Lquery::matches('foo_bar%', 'foo_bar_baz'))->toBeTrue();
    });

    it('matches foo_bar_baz_qux', function (): void {
        expect(Lquery::matches('foo_bar%', 'foo_bar_baz_qux'))->toBeTrue();
    });

    it('does NOT match foo_barbaz (no underscore)', function (): void {
        expect(Lquery::matches('foo_bar%', 'foo_barbaz'))->toBeFalse();
    });

    it('does NOT match foobar', function (): void {
        expect(Lquery::matches('foo_bar%', 'foobar'))->toBeFalse();
    });
});

describe('Acceptance Criteria: combined modifiers foo*@%', function (): void {
    it('matches foo exactly', function (): void {
        expect(Lquery::matches('foo*@%', 'foo'))->toBeTrue();
    });

    it('matches FOO (case insensitive)', function (): void {
        expect(Lquery::matches('foo*@%', 'FOO'))->toBeTrue();
    });

    it('matches foobar (prefix)', function (): void {
        expect(Lquery::matches('foo*@%', 'foobar'))->toBeTrue();
    });

    it('matches FOOBAR (prefix + case insensitive)', function (): void {
        expect(Lquery::matches('foo*@%', 'FOOBAR'))->toBeTrue();
    });

    it('matches foo_bar (word boundary)', function (): void {
        expect(Lquery::matches('foo*@%', 'foo_bar'))->toBeTrue();
    });

    it('matches FOO_BAR (word boundary + case insensitive)', function (): void {
        expect(Lquery::matches('foo*@%', 'FOO_BAR'))->toBeTrue();
    });

    it('matches foobar_baz (prefix + word boundary)', function (): void {
        expect(Lquery::matches('foo*@%', 'foobar_baz'))->toBeTrue();
    });

    it('matches FOOBAR_BAZ (all three)', function (): void {
        expect(Lquery::matches('foo*@%', 'FOOBAR_BAZ'))->toBeTrue();
    });
});

describe('Acceptance Criteria: negation with !admin', function (): void {
    it('matches bar', function (): void {
        expect(Lquery::matches('!admin', 'bar'))->toBeTrue();
    });

    it('matches foo', function (): void {
        expect(Lquery::matches('!admin', 'foo'))->toBeTrue();
    });

    it('matches root', function (): void {
        expect(Lquery::matches('!admin', 'root'))->toBeTrue();
    });

    it('does NOT match admin', function (): void {
        expect(Lquery::matches('!admin', 'admin'))->toBeFalse();
    });
});

describe('Acceptance Criteria: negation group with !admin|root', function (): void {
    it('matches bar', function (): void {
        expect(Lquery::matches('!admin|root', 'bar'))->toBeTrue();
    });

    it('matches foo', function (): void {
        expect(Lquery::matches('!admin|root', 'foo'))->toBeTrue();
    });

    it('matches user', function (): void {
        expect(Lquery::matches('!admin|root', 'user'))->toBeTrue();
    });

    it('does NOT match admin', function (): void {
        expect(Lquery::matches('!admin|root', 'admin'))->toBeFalse();
    });

    it('does NOT match root', function (): void {
        expect(Lquery::matches('!admin|root', 'root'))->toBeFalse();
    });
});

describe('Acceptance Criteria: alternatives with red|blue|green', function (): void {
    it('matches red', function (): void {
        expect(Lquery::matches('red|blue|green', 'red'))->toBeTrue();
    });

    it('matches blue', function (): void {
        expect(Lquery::matches('red|blue|green', 'blue'))->toBeTrue();
    });

    it('matches green', function (): void {
        expect(Lquery::matches('red|blue|green', 'green'))->toBeTrue();
    });

    it('does NOT match yellow', function (): void {
        expect(Lquery::matches('red|blue|green', 'yellow'))->toBeFalse();
    });

    it('does NOT match purple', function (): void {
        expect(Lquery::matches('red|blue|green', 'purple'))->toBeFalse();
    });
});

describe('Acceptance Criteria: Deprecated methods emit warnings but still function', function (): void {
    it('Deprecation utility emits warning when enabled', function (): void {
        Deprecation::enable();

        $triggered = false;
        $message = '';
        set_error_handler(function ($errno, $errstr) use (&$triggered, &$message) {
            if ($errno === E_USER_DEPRECATED) {
                $triggered = true;
                $message = $errstr;
            }

            return true;
        });

        Deprecation::methodRenamed('TestClass', 'oldMethod', 'newMethod');

        restore_error_handler();

        expect($triggered)->toBeTrue();
        expect($message)->toContain('deprecated');
    });

    it('Deprecation utility does not emit warning when disabled', function (): void {
        Deprecation::disable();

        $triggered = false;
        set_error_handler(function ($errno) use (&$triggered) {
            if ($errno === E_USER_DEPRECATED) {
                $triggered = true;
            }

            return true;
        });

        Deprecation::methodRenamed('TestClass', 'oldMethod', 'newMethod');

        restore_error_handler();
        Deprecation::enable(); // Re-enable for other tests

        expect($triggered)->toBeFalse();
    });
});
