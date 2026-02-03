<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\Label;
use Illuminate\Console\Command;

class LabelDeleteCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:label:delete
        {slug : The label slug to delete}
        {--force : Skip confirmation in production}';

    /** @var string */
    protected $description = 'Delete a label';

    public function handle(): int
    {
        /** @var string $slug */
        $slug = $this->argument('slug');
        $label = Label::where('slug', $slug)->first();

        if ($label === null) {
            $this->error('Label not found: '.$slug);

            return Command::FAILURE;
        }

        $relationshipCount = $label->relationships()->count() + $label->reverseRelationships()->count();

        if ($relationshipCount > 0) {
            $this->warn("This label has {$relationshipCount} relationship(s) that will be deleted.");
        }

        if (! $this->option('force') && ! $this->confirm("Delete label '{$label->name}'?")) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        $label->delete();

        $this->info("Label deleted: {$label->name}");

        return Command::SUCCESS;
    }
}
