<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\Label;
use Illuminate\Console\Command;

class LabelCreateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:label:create
        {name : The label name}
        {--slug= : Custom slug (auto-generated if not provided)}
        {--color= : Color in #RRGGBB format}
        {--icon= : Icon identifier}
        {--description= : Label description}';

    /** @var string */
    protected $description = 'Create a new label';

    public function handle(): int
    {
        $label = Label::create([
            'name' => $this->argument('name'),
            'slug' => $this->option('slug'),
            'color' => $this->option('color'),
            'icon' => $this->option('icon'),
            'description' => $this->option('description'),
        ]);

        $this->info("Label created: {$label->name} ({$label->slug})");

        return Command::SUCCESS;
    }
}
