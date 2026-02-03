<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\Label;
use Illuminate\Console\Command;

class LabelListCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:label:list';

    /** @var string */
    protected $description = 'List all labels';

    public function handle(): int
    {
        $labels = Label::orderBy('name')->get();

        if ($labels->isEmpty()) {
            $this->info('No labels found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Color', 'Icon', 'Relationships'],
            $labels->map(fn (Label $l): array => [
                $l->id,
                $l->name,
                $l->slug,
                $l->color ?? '-',
                $l->icon ?? '-',
                $l->relationships()->count().' children',
            ])
        );

        return Command::SUCCESS;
    }
}
