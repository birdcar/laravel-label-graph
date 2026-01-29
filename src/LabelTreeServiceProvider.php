<?php

declare(strict_types=1);

namespace Birdcar\LabelTree;

use Birdcar\LabelTree\Models\LabelRelationship;
use Birdcar\LabelTree\Observers\LabelRelationshipObserver;
use Birdcar\LabelTree\Services\CycleDetector;
use Birdcar\LabelTree\Services\RouteGenerator;
use Illuminate\Support\ServiceProvider;

class LabelTreeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/label-tree.php', 'label-tree');

        $this->app->singleton(CycleDetector::class);
        $this->app->singleton(RouteGenerator::class);
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
        ], 'label-tree-migrations');

        LabelRelationship::observe(LabelRelationshipObserver::class);
    }

    protected function getMigrationPath(string $filename): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$filename}");
    }
}
