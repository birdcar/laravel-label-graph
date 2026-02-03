<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;

beforeEach(function (): void {
    // Create test routes
    LabelRoute::create(['path' => 'bug', 'depth' => 0]);
    LabelRoute::create(['path' => 'priority', 'depth' => 0]);
    LabelRoute::create(['path' => 'priority.bug', 'depth' => 1]);
    LabelRoute::create(['path' => 'priority.high', 'depth' => 1]);
    LabelRoute::create(['path' => 'priority.high.bug', 'depth' => 2]);
    LabelRoute::create(['path' => 'status', 'depth' => 0]);
    LabelRoute::create(['path' => 'status.open', 'depth' => 1]);
    LabelRoute::create(['path' => 'status.open.bug', 'depth' => 2]);
});

describe('wherePathMatches with lquery semantics', function (): void {
    it('matches exact path', function (): void {
        $routes = LabelRoute::wherePathMatches('bug')->get();

        expect($routes)->toHaveCount(1);
        expect($routes->first()->path)->toBe('bug');
    });

    it('* matches zero or more labels (any path containing label)', function (): void {
        // *.bug matches any path ending with "bug" (including just "bug")
        $routes = LabelRoute::wherePathMatches('*.bug')->get();

        // Should match: bug, priority.bug, priority.high.bug, status.open.bug
        expect($routes)->toHaveCount(4);
        expect($routes->pluck('path')->toArray())
            ->toContain('bug')
            ->toContain('priority.bug')
            ->toContain('priority.high.bug')
            ->toContain('status.open.bug');
    });

    it('*.foo.* matches any path containing foo', function (): void {
        // *.bug.* matches any path containing "bug"
        $routes = LabelRoute::wherePathMatches('*.bug.*')->get();

        // Should match all paths containing "bug"
        expect($routes)->toHaveCount(4);
        expect($routes->pluck('path')->toArray())
            ->toContain('bug')
            ->toContain('priority.bug')
            ->toContain('priority.high.bug')
            ->toContain('status.open.bug');
    });

    it('foo.* matches foo followed by any labels', function (): void {
        // priority.* matches priority and anything under it
        $routes = LabelRoute::wherePathMatches('priority.*')->get();

        expect($routes)->toHaveCount(4);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority')
            ->toContain('priority.bug')
            ->toContain('priority.high')
            ->toContain('priority.high.bug');
    });

    it('*{1} matches exactly one label', function (): void {
        // *{1}.bug matches exactly "X.bug" (one label before bug)
        $routes = LabelRoute::wherePathMatches('*{1}.bug')->get();

        // Only matches: priority.bug (one label + bug)
        // Does NOT match: status.open.bug (two labels + bug)
        expect($routes)->toHaveCount(1);
        expect($routes->first()->path)->toBe('priority.bug');
    });

    it('*{2} matches exactly two labels', function (): void {
        $routes = LabelRoute::wherePathMatches('*{2}')->get();

        expect($routes)->toHaveCount(3);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority.bug')
            ->toContain('priority.high')
            ->toContain('status.open');
    });
});

describe('wherePathLike', function (): void {
    it('matches with LIKE pattern', function (): void {
        $routes = LabelRoute::wherePathLike('priority.%')->get();

        expect($routes)->toHaveCount(3);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority.bug')
            ->toContain('priority.high')
            ->toContain('priority.high.bug');
    });
});

describe('whereAncestorOf', function (): void {
    it('finds all ancestors of a path', function (): void {
        $routes = LabelRoute::whereAncestorOf('priority.high.bug')->get();

        expect($routes)->toHaveCount(2);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority')
            ->toContain('priority.high');
    });

    it('returns empty for root path', function (): void {
        $routes = LabelRoute::whereAncestorOf('bug')->get();

        expect($routes)->toHaveCount(0);
    });
});

describe('whereDescendantOf', function (): void {
    it('finds all descendants of a path', function (): void {
        $routes = LabelRoute::whereDescendantOf('priority')->get();

        expect($routes)->toHaveCount(3);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority.bug')
            ->toContain('priority.high')
            ->toContain('priority.high.bug');
    });

    it('returns empty for leaf path', function (): void {
        $routes = LabelRoute::whereDescendantOf('priority.high.bug')->get();

        expect($routes)->toHaveCount(0);
    });
});

describe('depth scopes', function (): void {
    it('filters by exact depth', function (): void {
        $routes = LabelRoute::whereDepth(0)->get();

        expect($routes)->toHaveCount(3);
        expect($routes->pluck('path')->toArray())
            ->toContain('bug')
            ->toContain('priority')
            ->toContain('status');
    });

    it('filters by depth range', function (): void {
        $routes = LabelRoute::whereDepthBetween(1, 2)->get();

        expect($routes)->toHaveCount(5);
    });

    it('filters by max depth', function (): void {
        $routes = LabelRoute::whereDepthLte(1)->get();

        expect($routes)->toHaveCount(6);
    });

    it('filters by min depth', function (): void {
        $routes = LabelRoute::whereDepthGte(2)->get();

        expect($routes)->toHaveCount(2);
        expect($routes->pluck('path')->toArray())
            ->toContain('priority.high.bug')
            ->toContain('status.open.bug');
    });
});

describe('instance methods', function (): void {
    it('gets ancestors', function (): void {
        $route = LabelRoute::where('path', 'priority.high.bug')->first();

        $ancestors = $route->ancestors();

        expect($ancestors)->toHaveCount(2);
        expect($ancestors->pluck('path')->toArray())
            ->toContain('priority')
            ->toContain('priority.high');
    });

    it('gets descendants', function (): void {
        $route = LabelRoute::where('path', 'priority')->first();

        $descendants = $route->descendants();

        expect($descendants)->toHaveCount(3);
    });

    it('gets parent', function (): void {
        $route = LabelRoute::where('path', 'priority.high.bug')->first();

        $parent = $route->parent();

        expect($parent)->not->toBeNull();
        expect($parent->path)->toBe('priority.high');
    });

    it('returns null parent for root', function (): void {
        $route = LabelRoute::where('path', 'priority')->first();

        expect($route->parent())->toBeNull();
    });

    it('gets direct children', function (): void {
        $route = LabelRoute::where('path', 'priority')->first();

        $children = $route->children();

        expect($children)->toHaveCount(2);
        expect($children->pluck('path')->toArray())
            ->toContain('priority.bug')
            ->toContain('priority.high');
    });

    it('checks if ancestor of another', function (): void {
        $priority = LabelRoute::where('path', 'priority')->first();
        $highBug = LabelRoute::where('path', 'priority.high.bug')->first();

        expect($priority->isAncestorOf($highBug))->toBeTrue();
        expect($highBug->isAncestorOf($priority))->toBeFalse();
    });

    it('checks if descendant of another', function (): void {
        $priority = LabelRoute::where('path', 'priority')->first();
        $highBug = LabelRoute::where('path', 'priority.high.bug')->first();

        expect($highBug->isDescendantOf($priority))->toBeTrue();
        expect($priority->isDescendantOf($highBug))->toBeFalse();
    });

    it('checks if root', function (): void {
        $root = LabelRoute::where('path', 'priority')->first();
        $child = LabelRoute::where('path', 'priority.high')->first();

        expect($root->isRoot())->toBeTrue();
        expect($child->isRoot())->toBeFalse();
    });

    it('checks if leaf', function (): void {
        $root = LabelRoute::where('path', 'priority')->first();
        $leaf = LabelRoute::where('path', 'priority.high.bug')->first();

        expect($root->isLeaf())->toBeFalse();
        expect($leaf->isLeaf())->toBeTrue();
    });

    it('accepts string path for ancestor check', function (): void {
        $route = LabelRoute::where('path', 'priority')->first();

        expect($route->isAncestorOf('priority.high.bug'))->toBeTrue();
    });

    it('accepts string path for descendant check', function (): void {
        $route = LabelRoute::where('path', 'priority.high.bug')->first();

        expect($route->isDescendantOf('priority'))->toBeTrue();
    });
});
