<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:install
        {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Install the Label Tree package';

    public function handle(): int
    {
        $this->info('Installing Label Tree...');

        $this->call('vendor:publish', [
            '--tag' => 'label-graph-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'label-graph-migrations',
            '--force' => $this->option('force'),
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('Label Tree installed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('  1. Create labels: php artisan label-graph:label:create "Bug"');
        $this->line('  2. Create relationships: php artisan label-graph:relationship:create parent-slug child-slug');
        $this->line('  3. Visualize: php artisan label-graph:visualize');

        return Command::SUCCESS;
    }
}
