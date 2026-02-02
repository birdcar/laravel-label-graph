<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query\Ltxtquery;

use InvalidArgumentException;

/**
 * Compiles AST to native PostgreSQL ltxtquery syntax.
 */
final class LtxtqueryCompiler
{
    public function compile(Token $ast): string
    {
        return $this->compileNode($ast);
    }

    private function compileNode(Token $node): string
    {
        return match ($node->type) {
            Token::TYPE_WORD => $this->compileWord($node),
            Token::TYPE_AND => $this->compileBinary($node, ' & '),
            Token::TYPE_OR => $this->compileBinary($node, ' | '),
            Token::TYPE_NOT => '!'.$this->compileNode($node->children[0]),
            Token::TYPE_GROUP => '('.$this->compileNode($node->children[0]).')',
            default => throw new InvalidArgumentException("Unknown token type: {$node->type}"),
        };
    }

    private function compileWord(Token $node): string
    {
        $result = $node->value ?? '';

        if ($node->prefixMatch) {
            $result .= '*';
        }
        if ($node->caseInsensitive) {
            $result .= '@';
        }
        if ($node->wordMatch) {
            $result .= '%';
        }

        return $result;
    }

    private function compileBinary(Token $node, string $operator): string
    {
        $left = $this->compileNode($node->children[0]);
        $right = $this->compileNode($node->children[1]);

        return $left.$operator.$right;
    }
}
