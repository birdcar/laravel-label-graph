<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph;

use Birdcar\LabelGraph\Console\InstallCommand;
use Birdcar\LabelGraph\Console\InstallLtreeCommand;
use Birdcar\LabelGraph\Console\LabelCreateCommand;
use Birdcar\LabelGraph\Console\LabelDeleteCommand;
use Birdcar\LabelGraph\Console\LabelListCommand;
use Birdcar\LabelGraph\Console\LabelUpdateCommand;
use Birdcar\LabelGraph\Console\RelationshipCreateCommand;
use Birdcar\LabelGraph\Console\RelationshipDeleteCommand;
use Birdcar\LabelGraph\Console\RelationshipListCommand;
use Birdcar\LabelGraph\Console\RouteListCommand;
use Birdcar\LabelGraph\Console\RoutePruneCommand;
use Birdcar\LabelGraph\Console\RouteRegenerateCommand;
use Birdcar\LabelGraph\Console\ValidateCommand;
use Birdcar\LabelGraph\Console\VisualizeCommand;
use Birdcar\LabelGraph\Models\LabelRelationship;
use Birdcar\LabelGraph\Observers\LabelRelationshipObserver;
use Birdcar\LabelGraph\Query\AdapterFactory;
use Birdcar\LabelGraph\Query\PathQueryAdapter;
use Birdcar\LabelGraph\Schema\LtreeIndex;
use Birdcar\LabelGraph\Services\CycleDetector;
use Birdcar\LabelGraph\Services\GraphValidator;
use Birdcar\LabelGraph\Services\GraphVisualizer;
use Birdcar\LabelGraph\Services\RouteGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class LabelGraphServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/label-graph.php', 'label-graph');

        $this->app->singleton(CycleDetector::class);
        $this->app->singleton(RouteGenerator::class);
        $this->app->singleton(AdapterFactory::class);
        $this->app->singleton(GraphVisualizer::class);
        $this->app->singleton(GraphValidator::class);

        $this->app->singleton(PathQueryAdapter::class, function ($app) {
            return $app->make(AdapterFactory::class)->make();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/label-graph.php' => config_path('label-graph.php'),
        ], 'label-graph-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_labels_table.php.stub' => $this->getMigrationPath('create_labels_table.php'),
            __DIR__.'/../database/migrations/create_label_relationships_table.php.stub' => $this->getMigrationPath('create_label_relationships_table.php'),
            __DIR__.'/../database/migrations/create_label_routes_table.php.stub' => $this->getMigrationPath('create_label_routes_table.php'),
            __DIR__.'/../database/migrations/create_labelables_table.php.stub' => $this->getMigrationPath('create_labelables_table.php'),
        ], 'label-graph-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                InstallLtreeCommand::class,
                LabelCreateCommand::class,
                LabelListCommand::class,
                LabelUpdateCommand::class,
                LabelDeleteCommand::class,
                RelationshipCreateCommand::class,
                RelationshipListCommand::class,
                RelationshipDeleteCommand::class,
                RouteListCommand::class,
                RouteRegenerateCommand::class,
                RoutePruneCommand::class,
                VisualizeCommand::class,
                ValidateCommand::class,
            ]);
        }

        LabelRelationship::observe(LabelRelationshipObserver::class);

        $this->registerSchemaMacros();
    }

    protected function registerSchemaMacros(): void
    {
        Blueprint::macro('ltreeIndex', function (string $column, ?string $name = null): void {
            /** @var Blueprint $this */
            LtreeIndex::create($this, $column, $name);
        });

        Blueprint::macro('ltreeGistIndex', function (string $column, ?string $name = null, int $siglen = 8): void {
            /** @var Blueprint $this */
            LtreeIndex::createGist($this, $column, $name, $siglen);
        });

        Blueprint::macro('dropLtreeIndex', function (string $column, ?string $name = null): void {
            /** @var Blueprint $this */
            LtreeIndex::drop($this, $column, $name);
        });
    }

    protected function getMigrationPath(string $filename): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$filename}");
    }
}
