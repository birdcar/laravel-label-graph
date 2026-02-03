<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Models\LabelRoute;
use Illuminate\Console\Command;

class RouteListCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:route:list
        {--filter= : Filter routes by path pattern}
        {--depth= : Filter by exact depth}
        {--min-depth= : Filter by minimum depth}
        {--max-depth= : Filter by maximum depth}';

    /** @var string */
    protected $description = 'List all label routes';

    public function handle(): int
    {
        $query = LabelRoute::query()->orderBy('path');

        if ($filter = $this->option('filter')) {
            /** @var string $filter */
            $query->where('path', 'LIKE', "%{$filter}%");
        }

        if ($depth = $this->option('depth')) {
            $query->where('depth', (int) $depth);
        }

        if ($minDepth = $this->option('min-depth')) {
            $query->where('depth', '>=', (int) $minDepth);
        }

        if ($maxDepth = $this->option('max-depth')) {
            $query->where('depth', '<=', (int) $maxDepth);
        }

        $routes = $query->get();

        if ($routes->isEmpty()) {
            $this->info('No routes found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Path', 'Depth', 'Attachments'],
            $routes->map(fn (LabelRoute $r): array => [
                $r->id,
                $r->path,
                $r->depth,
                $r->attachmentCount(),
            ])
        );

        $this->newLine();
        $this->info("Total: {$routes->count()} route(s)");

        return Command::SUCCESS;
    }
}
