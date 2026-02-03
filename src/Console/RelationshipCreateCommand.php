<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Exceptions\CycleDetectedException;
use Birdcar\LabelGraph\Exceptions\SelfReferenceException;
use Birdcar\LabelGraph\Models\Label;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class RelationshipCreateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:relationship:create
        {parent : Parent label slug}
        {child : Child label slug}';

    /** @var string */
    protected $description = 'Create a new relationship between two labels';

    public function handle(): int
    {
        /** @var string $parentSlug */
        $parentSlug = $this->argument('parent');
        /** @var string $childSlug */
        $childSlug = $this->argument('child');

        $parent = Label::where('slug', $parentSlug)->first();
        $child = Label::where('slug', $childSlug)->first();

        if ($parent === null) {
            $this->error('Parent label not found: '.$parentSlug);

            return Command::FAILURE;
        }

        if ($child === null) {
            $this->error('Child label not found: '.$childSlug);

            return Command::FAILURE;
        }

        try {
            LabelRelationship::create([
                'parent_label_id' => $parent->id,
                'child_label_id' => $child->id,
            ]);

            $this->info("Relationship created: {$parent->slug} -> {$child->slug}");

            return Command::SUCCESS;
        } catch (SelfReferenceException $e) {
            $this->error('Cannot create self-referential relationship.');

            return Command::FAILURE;
        } catch (CycleDetectedException $e) {
            $this->error('Cannot create relationship: would form a cycle.');

            return Command::FAILURE;
        } catch (QueryException $e) {
            // Handle unique constraint violations across databases
            // SQLite: "UNIQUE constraint failed"
            // MySQL: "Duplicate entry"
            // PostgreSQL: "duplicate key value violates unique constraint"
            $message = $e->getMessage();
            if (str_contains($message, 'UNIQUE constraint failed')
                || str_contains($message, 'Duplicate entry')
                || str_contains($message, 'duplicate key value violates unique constraint')) {
                $this->error('Relationship already exists.');

                return Command::FAILURE;
            }
            throw $e;
        }
    }
}
