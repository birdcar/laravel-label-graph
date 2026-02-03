<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\LabelRelationship;
use Illuminate\Console\Command;

class RelationshipListCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:relationship:list';

    /** @var string */
    protected $description = 'List all label relationships';

    public function handle(): int
    {
        $relationships = LabelRelationship::with(['parent', 'child'])->get();

        if ($relationships->isEmpty()) {
            $this->info('No relationships found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Parent', 'Child', 'Created At'],
            $relationships->map(function (LabelRelationship $r): array {
                /** @var \Birdcar\LabelGraph\Models\Label $parent */
                $parent = $r->parent;
                /** @var \Birdcar\LabelGraph\Models\Label $child */
                $child = $r->child;

                return [
                    $r->id,
                    $parent->slug,
                    $child->slug,
                    $r->created_at?->toDateTimeString() ?? '-',
                ];
            })
        );

        return Command::SUCCESS;
    }
}
