<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Services\GraphValidator;
use Illuminate\Console\Command;

class ValidateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:validate
        {--fix : Automatically fix safe issues}';

    /** @var string */
    protected $description = 'Validate label graph integrity';

    public function handle(GraphValidator $validator): int
    {
        $this->info('Validating label graph...');

        $issues = $validator->validate();

        if ($issues->isEmpty()) {
            $this->info('No issues found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$issues->count()} issue(s):");
        $this->newLine();

        foreach ($issues as $issue) {
            $this->line("  [{$issue['severity']}] {$issue['message']}");
            if (isset($issue['fix'])) {
                $this->line("    Fix: {$issue['fix']}");
            }
        }

        if ($this->option('fix')) {
            $this->newLine();
            $fixed = $validator->autoFix($issues);
            $this->info("Fixed {$fixed} issue(s).");
        }

        return Command::FAILURE;
    }
}
