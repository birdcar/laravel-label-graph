<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Manages query constraints for hierarchical queries.
 */
class ConstraintBuilder
{
    /** @var array<int, Closure> */
    protected array $queryConstraints = [];

    /** @var array<int, Closure> */
    protected array $initialConstraints = [];

    /** @var array<int, Closure> */
    protected array $traversalConstraints = [];

    public function addQueryConstraint(Closure $constraint): self
    {
        $this->queryConstraints[] = $constraint;

        return $this;
    }

    public function addInitialConstraint(Closure $constraint): self
    {
        $this->initialConstraints[] = $constraint;

        return $this;
    }

    public function addTraversalConstraint(Closure $constraint): self
    {
        $this->traversalConstraints[] = $constraint;

        return $this;
    }

    /**
     * Apply constraints to a query builder.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  'initial'|'traversal'|'both'  $phase
     */
    public function apply(Builder $query, string $phase = 'both'): void
    {
        // Always apply query constraints
        foreach ($this->queryConstraints as $constraint) {
            $constraint($query);
        }

        // Apply phase-specific constraints
        if ($phase === 'initial' || $phase === 'both') {
            foreach ($this->initialConstraints as $constraint) {
                $constraint($query);
            }
        }

        if ($phase === 'traversal' || $phase === 'both') {
            foreach ($this->traversalConstraints as $constraint) {
                $constraint($query);
            }
        }
    }

    public function hasConstraints(): bool
    {
        return ! empty($this->queryConstraints)
            || ! empty($this->initialConstraints)
            || ! empty($this->traversalConstraints);
    }

    public function clear(): void
    {
        $this->queryConstraints = [];
        $this->initialConstraints = [];
        $this->traversalConstraints = [];
    }
}
