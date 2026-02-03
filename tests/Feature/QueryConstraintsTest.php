<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;

beforeEach(function (): void {
    LabelRoute::create(['path' => 'active', 'depth' => 0]);
    LabelRoute::create(['path' => 'active.featured', 'depth' => 1]);
    LabelRoute::create(['path' => 'active.featured.popular', 'depth' => 2]);
    LabelRoute::create(['path' => 'active.archived', 'depth' => 1]);
    LabelRoute::create(['path' => 'active.archived.old', 'depth' => 2]);
    LabelRoute::create(['path' => 'inactive', 'depth' => 0]);
    LabelRoute::create(['path' => 'inactive.hidden', 'depth' => 1]);
});

describe('withQueryConstraint', function (): void {
    it('applies constraint to query', function (): void {
        $routes = LabelRoute::query()
            ->withQueryConstraint(fn ($q) => $q->where('path', 'not like', '%archived%'))
            ->whereDescendantOf('active')
            ->get();

        expect($routes)->toHaveCount(2);
        expect($routes->pluck('path')->toArray())->toContain(
            'active.featured',
            'active.featured.popular'
        );
        expect($routes->pluck('path')->toArray())->not->toContain('active.archived');
    });

    it('chains multiple constraints', function (): void {
        $routes = LabelRoute::query()
            ->withQueryConstraint(fn ($q) => $q->where('path', 'not like', '%archived%'))
            ->withQueryConstraint(fn ($q) => $q->where('depth', '>', 0))
            ->get();

        expect($routes->pluck('path')->toArray())
            ->not->toContain('active', 'inactive')
            ->not->toContain('active.archived');
    });
});

describe('withInitialConstraint', function (): void {
    it('filters initial selection', function (): void {
        $routes = LabelRoute::query()
            ->withInitialConstraint(fn ($q) => $q->where('path', 'like', 'active%'))
            ->whereIsRoot()
            ->get();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->path)->toBe('active');
    });
});

describe('withTraversalConstraint', function (): void {
    it('filters traversal results', function (): void {
        $routes = LabelRoute::query()
            ->whereDescendantOf('active')
            ->withTraversalConstraint(fn ($q) => $q->where('depth', '<', 2))
            ->get();

        expect($routes)->toHaveCount(2);
        expect($routes->pluck('depth')->unique()->toArray())->toBe([1]);
    });
});

describe('ancestorsWithConstraints', function (): void {
    it('applies traversal constraint to ancestors', function (): void {
        $route = LabelRoute::where('path', 'active.featured.popular')->first();

        $ancestors = $route->ancestorsWithConstraints(
            traversalConstraint: fn ($q) => $q->where('depth', '>', 0)
        );

        expect($ancestors)->toHaveCount(1);
        expect($ancestors->first()->path)->toBe('active.featured');
    });
});

describe('descendantsWithConstraints', function (): void {
    it('applies traversal constraint to descendants', function (): void {
        $route = LabelRoute::where('path', 'active')->first();

        $descendants = $route->descendantsWithConstraints(
            traversalConstraint: fn ($q) => $q->where('path', 'not like', '%archived%')
        );

        expect($descendants)->toHaveCount(2);
        expect($descendants->pluck('path')->toArray())->toContain(
            'active.featured',
            'active.featured.popular'
        );
    });
});

describe('constraint combinations', function (): void {
    it('combines all constraint types', function (): void {
        $routes = LabelRoute::query()
            ->withQueryConstraint(fn ($q) => $q->orderBy('path'))
            ->withInitialConstraint(fn ($q) => $q->where('path', 'like', 'active%'))
            ->withTraversalConstraint(fn ($q) => $q->where('path', 'not like', '%archived%'))
            ->get();

        $paths = $routes->pluck('path')->toArray();

        // Should only have active.* paths, excluding archived
        expect($paths)->toContain('active', 'active.featured', 'active.featured.popular');
        expect($paths)->not->toContain('inactive', 'active.archived');
    });
});
