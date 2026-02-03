<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Console;

use Birdcar\LabelGraph\Services\GraphVisualizer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class VisualizeCommand extends Command
{
    /** @var string */
    protected $signature = 'label-graph:visualize
        {--format=tree : Output format (tree, ascii, json)}
        {--routes : Include generated routes}';

    /** @var string */
    protected $description = 'Visualize the label graph';

    public function handle(GraphVisualizer $visualizer): int
    {
        /** @var string $format */
        $format = $this->option('format');
        $includeRoutes = (bool) $this->option('routes');

        try {
            $output = match ($format) {
                'tree' => $visualizer->renderTree($includeRoutes),
                'ascii' => $visualizer->renderAscii($includeRoutes),
                'json' => $visualizer->renderJson($includeRoutes),
                default => throw new InvalidArgumentException("Unknown format: {$format}"),
            };
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($output === '') {
            $this->info('No labels found.');

            return Command::SUCCESS;
        }

        $this->line($output);

        return Command::SUCCESS;
    }
}
