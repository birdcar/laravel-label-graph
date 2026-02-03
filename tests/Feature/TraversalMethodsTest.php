<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;

beforeEach(function (): void {
    // Create a DAG structure:
    //       root1          root2
    //         |              |
    //       child1        child2
    //         |    \      /
    //       grand1   shared

    LabelRoute::create(['path' => 'root1', 'depth' => 0]);
    LabelRoute::create(['path' => 'root2', 'depth' => 0]);
    LabelRoute::create(['path' => 'root1.child1', 'depth' => 1]);
    LabelRoute::create(['path' => 'root2.child2', 'depth' => 1]);
    LabelRoute::create(['path' => 'root1.child1.grand1', 'depth' => 2]);
    LabelRoute::create(['path' => 'root1.child1.shared', 'depth' => 2]);
    LabelRoute::create(['path' => 'root2.child2.shared', 'depth' => 2]);
});

describe('ancestorsAndSelf', function (): void {
    it('returns ancestors plus self ordered root-to-leaf', function (): void {
        $route = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $result = $route->ancestorsAndSelf();

        expect($result)->toHaveCount(3);
        expect($result->pluck('path')->toArray())->toBe([
            'root1',
            'root1.child1',
            'root1.child1.grand1',
        ]);
    });

    it('returns only self for root', function (): void {
        $route = LabelRoute::where('path', 'root1')->first();
        $result = $route->ancestorsAndSelf();

        expect($result)->toHaveCount(1);
        expect($result->first()->path)->toBe('root1');
    });
});

describe('descendantsAndSelf', function (): void {
    it('returns self plus descendants', function (): void {
        $route = LabelRoute::where('path', 'root1.child1')->first();
        $result = $route->descendantsAndSelf();

        expect($result)->toHaveCount(3);
        expect($result->pluck('path')->toArray())->toContain(
            'root1.child1',
            'root1.child1.grand1',
            'root1.child1.shared',
        );
    });
});

describe('siblings', function (): void {
    it('returns other children of same parent', function (): void {
        $route = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $siblings = $route->siblings();

        expect($siblings)->toHaveCount(1);
        expect($siblings->first()->path)->toBe('root1.child1.shared');
    });

    it('returns other roots for root nodes', function (): void {
        $route = LabelRoute::where('path', 'root1')->first();
        $siblings = $route->siblings();

        expect($siblings)->toHaveCount(1);
        expect($siblings->first()->path)->toBe('root2');
    });
});

describe('siblingsAndSelf', function (): void {
    it('includes current route', function (): void {
        $route = LabelRoute::where('path', 'root1')->first();
        $result = $route->siblingsAndSelf();

        expect($result)->toHaveCount(2);
        expect($result->pluck('path')->toArray())->toContain('root1', 'root2');
    });
});

describe('rootAncestors', function (): void {
    it('returns root ancestor', function (): void {
        $route = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $roots = $route->rootAncestors();

        expect($roots)->toHaveCount(1);
        expect($roots->first()->path)->toBe('root1');
    });
});

describe('bloodline', function (): void {
    it('returns complete lineage', function (): void {
        $route = LabelRoute::where('path', 'root1.child1')->first();
        $bloodline = $route->bloodline();

        expect($bloodline)->toHaveCount(4);
        expect($bloodline->pluck('path')->toArray())->toContain(
            'root1',           // ancestor
            'root1.child1',    // self
            'root1.child1.grand1',  // descendant
            'root1.child1.shared',  // descendant
        );
    });
});

describe('isChildOf and isParentOf', function (): void {
    it('isChildOf returns true for descendants', function (): void {
        $grand = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $root = LabelRoute::where('path', 'root1')->first();

        expect($grand->isChildOf($root))->toBeTrue();
        expect($grand->isChildOf('root1'))->toBeTrue();
        expect($root->isChildOf($grand))->toBeFalse();
    });

    it('isParentOf returns true for ancestors', function (): void {
        $root = LabelRoute::where('path', 'root1')->first();
        $grand = LabelRoute::where('path', 'root1.child1.grand1')->first();

        expect($root->isParentOf($grand))->toBeTrue();
        expect($root->isParentOf('root1.child1.grand1'))->toBeTrue();
        expect($grand->isParentOf($root))->toBeFalse();
    });
});

describe('getDepthRelatedTo', function (): void {
    it('returns positive for descendants', function (): void {
        $grand = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $root = LabelRoute::where('path', 'root1')->first();

        expect($grand->getDepthRelatedTo($root))->toBe(2);
    });

    it('returns negative for ancestors', function (): void {
        $root = LabelRoute::where('path', 'root1')->first();
        $grand = LabelRoute::where('path', 'root1.child1.grand1')->first();

        expect($root->getDepthRelatedTo($grand))->toBe(-2);
    });

    it('returns null for unrelated routes', function (): void {
        $root1Grand = LabelRoute::where('path', 'root1.child1.grand1')->first();
        $root2Child = LabelRoute::where('path', 'root2.child2')->first();

        expect($root1Grand->getDepthRelatedTo($root2Child))->toBeNull();
    });

    it('accepts string path', function (): void {
        $grand = LabelRoute::where('path', 'root1.child1.grand1')->first();

        expect($grand->getDepthRelatedTo('root1'))->toBe(2);
    });
});

describe('query scopes', function (): void {
    it('whereIsRoot filters to depth 0', function (): void {
        $roots = LabelRoute::whereIsRoot()->get();

        expect($roots)->toHaveCount(2);
        expect($roots->pluck('path')->toArray())->toContain('root1', 'root2');
    });

    it('whereHasChildren filters to non-leaves', function (): void {
        $withChildren = LabelRoute::whereHasChildren()->get();

        expect($withChildren->pluck('path')->toArray())
            ->toContain('root1', 'root2', 'root1.child1', 'root2.child2')
            ->not->toContain('root1.child1.grand1', 'root1.child1.shared');
    });

    it('whereHasParent filters to non-roots', function (): void {
        $withParent = LabelRoute::whereHasParent()->get();

        expect($withParent)->toHaveCount(5);
        expect($withParent->pluck('path')->toArray())
            ->not->toContain('root1', 'root2');
    });
});
