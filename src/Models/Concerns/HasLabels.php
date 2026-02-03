<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Models\Concerns;

use Birdcar\LabelGraph\Exceptions\InvalidRouteException;
use Birdcar\LabelGraph\Models\Labelable;
use Birdcar\LabelGraph\Models\LabelRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait for models that can be labeled with routes.
 *
 * @property-read array<int, string> $label_paths
 *
 * @method static Builder<static> whereHasRoute(string $path)
 * @method static Builder<static> whereHasRouteMatching(string $pattern)
 * @method static Builder<static> whereHasRouteDescendantOf(string $path)
 * @method static Builder<static> whereHasRouteAncestorOf(string $path)
 * @method static Builder<static> withRoutesCount()
 * @method static Builder<static> withRoutes()
 * @method static Builder<static> whereHasRouteOrDescendant(string $path)
 * @method static Builder<static> whereHasRouteOrAncestor(string $path)
 * @method static Builder<static> whereHasRouteInSubtrees(array<int, string> $paths)
 */
trait HasLabels
{
    /**
     * Get all attached routes.
     *
     * @return MorphToMany<LabelRoute, $this>
     */
    public function labelRoutes(): MorphToMany
    {
        return $this->morphToMany(
            LabelRoute::class,
            'labelable',
            config('label-graph.tables.labelables', 'labelables'),
            'labelable_id',
            'label_route_id'
        )->using(Labelable::class)->withTimestamps();
    }

    /**
     * Attach a route by path or model.
     */
    public function attachRoute(LabelRoute|string $route): void
    {
        $routeModel = $this->resolveRoute($route);
        $this->labelRoutes()->syncWithoutDetaching([$routeModel->id]);
    }

    /**
     * Detach a route by path or model.
     */
    public function detachRoute(LabelRoute|string $route): void
    {
        $routeModel = $this->resolveRoute($route);
        $this->labelRoutes()->detach($routeModel->id);
    }

    /**
     * Sync routes (replace all attached routes).
     *
     * @param  array<int, LabelRoute|string>  $routes
     */
    public function syncRoutes(array $routes): void
    {
        $routeIds = collect($routes)->map(function (LabelRoute|string $route): string {
            return $this->resolveRoute($route)->id;
        })->all();

        $this->labelRoutes()->sync($routeIds);
    }

    /**
     * Check if a specific route is attached.
     */
    public function hasRoute(LabelRoute|string $route): bool
    {
        $routeModel = $this->resolveRoute($route);
        $routeTable = config('label-graph.tables.routes', 'label_routes');

        return $this->labelRoutes()->where("{$routeTable}.id", $routeModel->id)->exists();
    }

    /**
     * Check if any attached route matches a pattern.
     */
    public function hasRouteMatching(string $pattern): bool
    {
        return $this->labelRoutes()
            ->wherePathMatches($pattern)
            ->exists();
    }

    /**
     * Get all attached route paths as array.
     *
     * @return array<int, string>
     */
    public function getLabelPathsAttribute(): array
    {
        return $this->labelRoutes->pluck('path')->all();
    }

    /**
     * Resolve a route from path string or model.
     */
    protected function resolveRoute(LabelRoute|string $route): LabelRoute
    {
        if ($route instanceof LabelRoute) {
            return $route;
        }

        $found = LabelRoute::where('path', $route)->first();

        if (! $found) {
            throw new InvalidRouteException("Route not found: {$route}");
        }

        return $found;
    }

    /**
     * Scope: models with exact route attached.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRoute(Builder $query, string $path): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($path): void {
            $q->where('path', $path);
        });
    }

    /**
     * Scope: models with routes matching pattern.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRouteMatching(Builder $query, string $pattern): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($pattern): void {
            /** @var Builder<LabelRoute> $q */
            $q->wherePathMatches($pattern);
        });
    }

    /**
     * Scope: models with routes descending from path.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRouteDescendantOf(Builder $query, string $path): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($path): void {
            /** @var Builder<LabelRoute> $q */
            $q->whereDescendantOf($path);
        });
    }

    /**
     * Scope: models with routes that are ancestors of path.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRouteAncestorOf(Builder $query, string $path): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($path): void {
            /** @var Builder<LabelRoute> $q */
            $q->whereAncestorOf($path);
        });
    }

    /**
     * Scope: eager load routes count.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithRoutesCount(Builder $query): Builder
    {
        return $query->withCount('labelRoutes');
    }

    /**
     * Scope: eager load routes.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithRoutes(Builder $query): Builder
    {
        return $query->with('labelRoutes');
    }

    /**
     * Scope: models with this route OR any descendant route.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRouteOrDescendant(Builder $query, string $path): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($path): void {
            /** @var Builder<LabelRoute> $q */
            $q->where(function ($inner) use ($path) {
                $inner->where('path', $path)
                    ->orWhere(function ($sub) use ($path) {
                        /** @var Builder<LabelRoute> $sub */
                        $sub->whereDescendantOf($path);
                    });
            });
        });
    }

    /**
     * Scope: models with this route OR any ancestor route.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereHasRouteOrAncestor(Builder $query, string $path): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($path): void {
            /** @var Builder<LabelRoute> $q */
            $q->where(function ($inner) use ($path) {
                $inner->where('path', $path)
                    ->orWhere(function ($sub) use ($path) {
                        /** @var Builder<LabelRoute> $sub */
                        $sub->whereAncestorOf($path);
                    });
            });
        });
    }

    /**
     * Scope: models matching any route in the given subtree paths.
     *
     * @param  Builder<static>  $query
     * @param  array<int, string>  $paths
     * @return Builder<static>
     */
    public function scopeWhereHasRouteInSubtrees(Builder $query, array $paths): Builder
    {
        return $query->whereHas('labelRoutes', function (Builder $q) use ($paths): void {
            $q->where(function ($inner) use ($paths) {
                foreach ($paths as $path) {
                    $inner->orWhere(function ($sub) use ($path) {
                        $sub->where('path', $path)
                            ->orWhere(function ($desc) use ($path) {
                                /** @var Builder<LabelRoute> $desc */
                                $desc->whereDescendantOf($path);
                            });
                    });
                }
            });
        });
    }
}
