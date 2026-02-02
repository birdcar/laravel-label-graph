<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query\Ltxtquery;

use Birdcar\LabelTree\Exceptions\LtxtqueryParseException;

/**
 * Parses ltxtquery boolean expressions into an AST.
 *
 * Grammar:
 *   expr     = term (('|' | 'OR') term)*
 *   term     = factor (('&' | 'AND') factor)*
 *   factor   = '!' factor | '(' expr ')' | word
 *   word     = [A-Za-z0-9_-]+ modifiers?
 *   modifiers = [@*%]+
 */
final class Parser
{
    private string $input;

    private int $pos;

    private int $length;

    public function parse(string $pattern): Token
    {
        $this->input = trim($pattern);
        $this->pos = 0;
        $this->length = strlen($this->input);

        if ($this->length === 0) {
            throw LtxtqueryParseException::emptyPattern();
        }

        $ast = $this->parseExpr();
        $this->skipWhitespace();

        if ($this->pos < $this->length) {
            throw LtxtqueryParseException::unexpectedChar($this->input[$this->pos], $this->pos);
        }

        return $ast;
    }

    private function parseExpr(): Token
    {
        $left = $this->parseTerm();

        while ($this->matchOperator('|')) {
            $right = $this->parseTerm();
            $left = Token::or($left, $right);
        }

        return $left;
    }

    private function parseTerm(): Token
    {
        $left = $this->parseFactor();

        while ($this->matchOperator('&')) {
            $right = $this->parseFactor();
            $left = Token::and($left, $right);
        }

        return $left;
    }

    private function parseFactor(): Token
    {
        $this->skipWhitespace();

        if ($this->match('!')) {
            return Token::not($this->parseFactor());
        }

        if ($this->match('(')) {
            $inner = $this->parseExpr();
            if (! $this->match(')')) {
                throw LtxtqueryParseException::missingCloseParen($this->pos);
            }

            return Token::group($inner);
        }

        return $this->parseWord();
    }

    private function parseWord(): Token
    {
        $this->skipWhitespace();
        $start = $this->pos;

        // Match word characters
        while ($this->pos < $this->length &&
               preg_match('/[A-Za-z0-9_-]/', $this->input[$this->pos])) {
            $this->pos++;
        }

        if ($this->pos === $start) {
            throw LtxtqueryParseException::expectedWord($this->pos);
        }

        $value = substr($this->input, $start, $this->pos - $start);

        // Parse modifiers
        $caseInsensitive = false;
        $prefixMatch = false;
        $wordMatch = false;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            if ($char === '@') {
                $caseInsensitive = true;
                $this->pos++;
            } elseif ($char === '*') {
                $prefixMatch = true;
                $this->pos++;
            } elseif ($char === '%') {
                $wordMatch = true;
                $this->pos++;
            } else {
                break;
            }
        }

        return Token::word($value, $caseInsensitive, $prefixMatch, $wordMatch);
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && ctype_space($this->input[$this->pos])) {
            $this->pos++;
        }
    }

    private function match(string $char): bool
    {
        $this->skipWhitespace();
        if ($this->pos < $this->length && $this->input[$this->pos] === $char) {
            $this->pos++;

            return true;
        }

        return false;
    }

    private function matchOperator(string $op): bool
    {
        $this->skipWhitespace();
        if ($this->pos >= $this->length) {
            return false;
        }

        if ($this->input[$this->pos] === $op) {
            $this->pos++;

            return true;
        }

        return false;
    }
}
