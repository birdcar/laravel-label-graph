<?php

declare(strict_types=1);

namespace Birdcar\LabelTree;

use Birdcar\LabelTree\Console\InstallCommand;
use Birdcar\LabelTree\Console\LabelCreateCommand;
use Birdcar\LabelTree\Console\LabelDeleteCommand;
use Birdcar\LabelTree\Console\LabelListCommand;
use Birdcar\LabelTree\Console\LabelUpdateCommand;
use Birdcar\LabelTree\Console\RelationshipCreateCommand;
use Birdcar\LabelTree\Console\RelationshipDeleteCommand;
use Birdcar\LabelTree\Console\RelationshipListCommand;
use Birdcar\LabelTree\Console\RouteListCommand;
use Birdcar\LabelTree\Console\RoutePruneCommand;
use Birdcar\LabelTree\Console\RouteRegenerateCommand;
use Birdcar\LabelTree\Console\ValidateCommand;
use Birdcar\LabelTree\Console\VisualizeCommand;
use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Observers\LabelRelationshipObserver;
use Birdcar\LabelTree\Query\AdapterFactory;
use Birdcar\LabelTree\Query\PathQueryAdapter;
use Birdcar\LabelTree\Services\CycleDetector;
use Birdcar\LabelTree\Services\GraphValidator;
use Birdcar\LabelTree\Services\GraphVisualizer;
use Birdcar\LabelTree\Services\RouteGenerator;
use Illuminate\Support\ServiceProvider;

class LabelTreeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/label-tree.php', 'label-tree');

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
            __DIR__.'/../config/label-tree.php' => config_path('label-tree.php'),
        ], 'label-tree-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_labels_table.php.stub' => $this->getMigrationPath('create_labels_table.php'),
            __DIR__.'/../database/migrations/create_label_relationships_table.php.stub' => $this->getMigrationPath('create_label_relationships_table.php'),
            __DIR__.'/../database/migrations/create_label_routes_table.php.stub' => $this->getMigrationPath('create_label_routes_table.php'),
            __DIR__.'/../database/migrations/create_labelables_table.php.stub' => $this->getMigrationPath('create_labelables_table.php'),
        ], 'label-tree-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
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
    }

    protected function getMigrationPath(string $filename): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$filename}");
    }
}
