<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallLtreeCommand extends Command
{
    /** @var string */
    protected $signature = 'label-tree:install-ltree
                            {--check : Only check if ltree is installed, do not install}';

    /** @var string */
    protected $description = 'Install the PostgreSQL ltree extension for improved performance';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            $this->error('This command only works with PostgreSQL.');
            $this->line('Current driver: '.$driver);

            return self::FAILURE;
        }

        // Check current status
        $installed = $this->isLtreeInstalled();

        if ($this->option('check')) {
            if ($installed) {
                $this->info('✓ ltree extension is installed');

                return self::SUCCESS;
            }

            $this->warn('✗ ltree extension is NOT installed');
            $this->line('Run without --check to install it.');

            return self::FAILURE;
        }

        if ($installed) {
            $this->info('ltree extension is already installed.');

            return self::SUCCESS;
        }

        // Attempt installation
        $this->line('Installing ltree extension...');

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');
            $this->info('✓ ltree extension installed successfully');

            $this->newLine();
            $this->line('Benefits of ltree:');
            $this->line('  • Native path matching with GiST index support');
            $this->line('  • Array operators for batch queries');
            $this->line('  • Optimized ancestor/descendant queries');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to install ltree extension.');
            $this->newLine();
            $this->line('This may be due to:');
            $this->line('  1. Insufficient database privileges (requires CREATE privilege)');
            $this->line('  2. Extension not available on your PostgreSQL installation');
            $this->line('  3. Managed database requiring extension enable via dashboard');
            $this->newLine();
            $this->line('Error: '.$e->getMessage());
            $this->newLine();
            $this->line('Manual installation:');
            $this->line('  psql -d your_database -c "CREATE EXTENSION IF NOT EXISTS ltree;"');

            return self::FAILURE;
        }
    }

    private function isLtreeInstalled(): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'ltree'");

            return count($result) > 0;
        } catch (\Exception) {
            return false;
        }
    }
}
