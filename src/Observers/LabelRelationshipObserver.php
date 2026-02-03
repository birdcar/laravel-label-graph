<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Observers;

use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Services\RouteGenerator;

class LabelRelationshipObserver
{
    public function __construct(
        protected RouteGenerator $routeGenerator
    ) {}

    public function created(LabelRelationship $relationship): void
    {
        $this->routeGenerator->generateForRelationship($relationship);
    }

    public function deleted(LabelRelationship $relationship): void
    {
        $this->routeGenerator->pruneForDeletedRelationship($relationship);
    }
}
