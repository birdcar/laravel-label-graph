<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\Lquery\Parser;
use Birdcar\LabelTree\Query\Lquery\Token;

beforeEach(function (): void {
    $this->parser = new Parser;
});

describe('parsing simple patterns', function (): void {
    it('parses a single label', function (): void {
        $tokens = $this->parser->parse('foo');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_LABEL);
        expect($tokens[0]->value)->toBe('foo');
    });

    it('parses multiple labels', function (): void {
        $tokens = $this->parser->parse('foo.bar.baz');

        expect($tokens)->toHaveCount(3);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[1]->value)->toBe('bar');
        expect($tokens[2]->value)->toBe('baz');
    });

    it('parses labels with underscores and hyphens', function (): void {
        $tokens = $this->parser->parse('foo_bar.baz-qux');

        expect($tokens)->toHaveCount(2);
        expect($tokens[0]->value)->toBe('foo_bar');
        expect($tokens[1]->value)->toBe('baz-qux');
    });

    it('parses numeric labels', function (): void {
        $tokens = $this->parser->parse('42.123');

        expect($tokens)->toHaveCount(2);
        expect($tokens[0]->value)->toBe('42');
        expect($tokens[1]->value)->toBe('123');
    });
});

describe('parsing star patterns', function (): void {
    it('parses a single star as zero or more labels', function (): void {
        $tokens = $this->parser->parse('*');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_STAR);
        expect($tokens[0]->getEffectiveMin())->toBe(0);
        expect($tokens[0]->getEffectiveMax())->toBeNull();
    });

    it('parses star with exact quantifier', function (): void {
        $tokens = $this->parser->parse('*{2}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_STAR);
        expect($tokens[0]->getEffectiveMin())->toBe(2);
        expect($tokens[0]->getEffectiveMax())->toBe(2);
    });

    it('parses star with min quantifier', function (): void {
        $tokens = $this->parser->parse('*{2,}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->getEffectiveMin())->toBe(2);
        expect($tokens[0]->getEffectiveMax())->toBeNull();
    });

    it('parses star with range quantifier', function (): void {
        $tokens = $this->parser->parse('*{2,5}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->getEffectiveMin())->toBe(2);
        expect($tokens[0]->getEffectiveMax())->toBe(5);
    });

    it('parses star with max quantifier', function (): void {
        $tokens = $this->parser->parse('*{,3}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->getEffectiveMin())->toBe(0);
        expect($tokens[0]->getEffectiveMax())->toBe(3);
    });

    it('parses star with unlimited quantifier', function (): void {
        $tokens = $this->parser->parse('*{,}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->getEffectiveMin())->toBe(0);
        expect($tokens[0]->getEffectiveMax())->toBeNull();
    });

    it('parses star in pattern', function (): void {
        $tokens = $this->parser->parse('*.foo.*');

        expect($tokens)->toHaveCount(3);
        expect($tokens[0]->type)->toBe(Token::TYPE_STAR);
        expect($tokens[1]->type)->toBe(Token::TYPE_LABEL);
        expect($tokens[2]->type)->toBe(Token::TYPE_STAR);
    });
});

describe('parsing label modifiers', function (): void {
    it('parses case-insensitive modifier', function (): void {
        $tokens = $this->parser->parse('foo@');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->caseInsensitive)->toBeTrue();
    });

    it('parses prefix modifier', function (): void {
        $tokens = $this->parser->parse('foo*');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->prefixMatch)->toBeTrue();
    });

    it('parses word match modifier', function (): void {
        $tokens = $this->parser->parse('foo%');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->wordMatch)->toBeTrue();
    });

    it('parses combined modifiers', function (): void {
        $tokens = $this->parser->parse('foo*@%');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->prefixMatch)->toBeTrue();
        expect($tokens[0]->caseInsensitive)->toBeTrue();
        expect($tokens[0]->wordMatch)->toBeTrue();
    });

    it('parses modifier with quantifier', function (): void {
        $tokens = $this->parser->parse('foo@{2,3}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->caseInsensitive)->toBeTrue();
        expect($tokens[0]->getEffectiveMin())->toBe(2);
        expect($tokens[0]->getEffectiveMax())->toBe(3);
    });
});

describe('parsing groups', function (): void {
    it('parses OR group', function (): void {
        $tokens = $this->parser->parse('foo|bar');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_GROUP);
        expect($tokens[0]->negated)->toBeFalse();
        expect($tokens[0]->alternatives)->toHaveCount(2);
        expect($tokens[0]->alternatives[0]['value'])->toBe('foo');
        expect($tokens[0]->alternatives[1]['value'])->toBe('bar');
    });

    it('parses NOT group', function (): void {
        $tokens = $this->parser->parse('!foo|bar');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_GROUP);
        expect($tokens[0]->negated)->toBeTrue();
        expect($tokens[0]->alternatives)->toHaveCount(2);
    });

    it('parses group with modifiers', function (): void {
        $tokens = $this->parser->parse('foo@|bar*');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->alternatives[0]['caseInsensitive'])->toBeTrue();
        expect($tokens[0]->alternatives[1]['prefixMatch'])->toBeTrue();
    });

    it('parses group with quantifier', function (): void {
        $tokens = $this->parser->parse('!foo|bar{2,}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->getEffectiveMin())->toBe(2);
        expect($tokens[0]->getEffectiveMax())->toBeNull();
    });
});

describe('error handling', function (): void {
    it('throws on empty pattern', function (): void {
        $this->parser->parse('');
    })->throws(InvalidArgumentException::class, 'Empty lquery pattern');

    it('throws on empty element', function (): void {
        $this->parser->parse('foo..bar');
    })->throws(InvalidArgumentException::class, 'Empty element');

    it('throws on invalid characters', function (): void {
        $this->parser->parse('foo/bar');
    })->throws(InvalidArgumentException::class, 'Invalid label characters');
});

describe('edge cases', function (): void {
    it('parses *{0} as zero labels', function (): void {
        $tokens = $this->parser->parse('*{0}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_STAR);
        expect($tokens[0]->getEffectiveMin())->toBe(0);
        expect($tokens[0]->getEffectiveMax())->toBe(0);
    });

    it('parses foo{,} as zero or more foo labels', function (): void {
        $tokens = $this->parser->parse('foo{,}');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_LABEL);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->getEffectiveMin())->toBe(0);
        expect($tokens[0]->getEffectiveMax())->toBeNull();
    });

    it('parses all three modifiers combined', function (): void {
        $tokens = $this->parser->parse('foo@*%');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->value)->toBe('foo');
        expect($tokens[0]->caseInsensitive)->toBeTrue();
        expect($tokens[0]->prefixMatch)->toBeTrue();
        expect($tokens[0]->wordMatch)->toBeTrue();
    });

    it('parses negated group with multiple alternatives', function (): void {
        $tokens = $this->parser->parse('!admin|root|system');

        expect($tokens)->toHaveCount(1);
        expect($tokens[0]->type)->toBe(Token::TYPE_GROUP);
        expect($tokens[0]->negated)->toBeTrue();
        expect($tokens[0]->alternatives)->toHaveCount(3);
        expect($tokens[0]->alternatives[0]['value'])->toBe('admin');
        expect($tokens[0]->alternatives[1]['value'])->toBe('root');
        expect($tokens[0]->alternatives[2]['value'])->toBe('system');
    });
});
