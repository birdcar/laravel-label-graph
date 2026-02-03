<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\LabelRoute;
use Birdcar\LabelGraph\Services\GraphValidator;
use Illuminate\Console\Command;

class RoutePruneCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:route:prune
        {--force : Skip confirmation}';

    /** @var string */
    protected $description = 'Remove orphaned routes that are no longer valid';

    public function handle(GraphValidator $validator): int
    {
        $validPaths = $validator->computeValidPaths();

        $orphaned = LabelRoute::whereNotIn('path', $validPaths)->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned routes found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$orphaned->count()} orphaned route(s):");
        foreach ($orphaned as $route) {
            $this->line("  - {$route->path}");
        }

        // Check for attachments
        $attachmentCount = 0;
        foreach ($orphaned as $route) {
            $attachmentCount += $route->attachmentCount();
        }

        if ($attachmentCount > 0) {
            $this->error("Cannot prune: {$attachmentCount} attachment(s) exist on orphaned routes.");
            $this->line('Use --force to delete anyway, or migrate attachments first.');

            if (! $this->option('force')) {
                return Command::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm('Delete these routes?')) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        $deleted = LabelRoute::whereNotIn('path', $validPaths)->delete();

        $this->info("Deleted {$deleted} orphaned route(s).");

        return Command::SUCCESS;
    }
}
