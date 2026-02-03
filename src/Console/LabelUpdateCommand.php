<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\Label;
use Illuminate\Console\Command;

class LabelUpdateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:label:update
        {slug : The label slug to update}
        {--name= : New name}
        {--new-slug= : New slug}
        {--color= : New color in #RRGGBB format}
        {--icon= : New icon identifier}
        {--description= : New description}';

    /** @var string */
    protected $description = 'Update a label';

    public function handle(): int
    {
        /** @var string $slug */
        $slug = $this->argument('slug');
        $label = Label::where('slug', $slug)->first();

        if ($label === null) {
            $this->error('Label not found: '.$slug);

            return Command::FAILURE;
        }

        $updates = array_filter([
            'name' => $this->option('name'),
            'slug' => $this->option('new-slug'),
            'color' => $this->option('color'),
            'icon' => $this->option('icon'),
            'description' => $this->option('description'),
        ], fn (mixed $v): bool => $v !== null);

        if (empty($updates)) {
            $this->warn('No updates provided.');

            return Command::SUCCESS;
        }

        $label->update($updates);

        $this->info("Label updated: {$label->name} ({$label->slug})");

        return Command::SUCCESS;
    }
}
