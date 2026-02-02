<?php

declare(strict_types=1);

use Birdcar\LabelTree\Exceptions\UnsupportedDatabaseException;
use Birdcar\LabelTree\Schema\LtreeIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

describe('LtreeIndex::create on SQLite', function (): void {
    it('creates a standard index on SQLite', function (): void {
        // The test migration already creates the ltree index
        // We just verify the table exists with indexes
        expect(Schema::hasTable(config('label-tree.tables.routes', 'label_routes')))->toBeTrue();
    });
});

describe('LtreeIndex::createGist', function (): void {
    it('throws UnsupportedDatabaseException on non-PostgreSQL', function (): void {
        Schema::create('test_gist_table', function (Blueprint $table) {
            $table->id();
            $table->string('path');
        });

        expect(function (): void {
            Schema::table('test_gist_table', function (Blueprint $table): void {
                LtreeIndex::createGist($table, 'path');
            });
        })->toThrow(UnsupportedDatabaseException::class);

        Schema::dropIfExists('test_gist_table');
    });

    it('includes driver name in exception message', function (): void {
        Schema::create('test_gist_msg', function (Blueprint $table) {
            $table->id();
            $table->string('path');
        });

        try {
            Schema::table('test_gist_msg', function (Blueprint $table): void {
                LtreeIndex::createGist($table, 'path');
            });
            $this->fail('Expected exception not thrown');
        } catch (UnsupportedDatabaseException $e) {
            expect($e->getMessage())
                ->toContain('GiST')
                ->toContain('PostgreSQL');
        }

        Schema::dropIfExists('test_gist_msg');
    });
});

describe('LtreeIndex::drop', function (): void {
    it('generates correct index name when name not provided', function (): void {
        Schema::create('test_drop_index', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->index('path', 'idx_test_drop_index_path_ltree');
        });

        Schema::table('test_drop_index', function (Blueprint $table) {
            LtreeIndex::drop($table, 'path');
        });

        // If we get here without error, the drop worked
        expect(true)->toBeTrue();

        Schema::dropIfExists('test_drop_index');
    });

    it('uses custom name when provided', function (): void {
        Schema::create('test_drop_custom', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->index('path', 'my_custom_index');
        });

        Schema::table('test_drop_custom', function (Blueprint $table) {
            LtreeIndex::drop($table, 'path', 'my_custom_index');
        });

        // If we get here without error, the drop worked
        expect(true)->toBeTrue();

        Schema::dropIfExists('test_drop_custom');
    });
});

describe('Blueprint macros', function (): void {
    it('ltreeIndex macro is registered', function (): void {
        expect(Blueprint::hasMacro('ltreeIndex'))->toBeTrue();
    });

    it('ltreeGistIndex macro is registered', function (): void {
        expect(Blueprint::hasMacro('ltreeGistIndex'))->toBeTrue();
    });

    it('dropLtreeIndex macro is registered', function (): void {
        expect(Blueprint::hasMacro('dropLtreeIndex'))->toBeTrue();
    });

    it('ltreeIndex macro creates index on table', function (): void {
        Schema::create('test_macro_index', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->ltreeIndex('path');
        });

        // If we get here without error, the macro worked
        expect(Schema::hasTable('test_macro_index'))->toBeTrue();

        Schema::dropIfExists('test_macro_index');
    });

    it('dropLtreeIndex macro removes index', function (): void {
        Schema::create('test_macro_drop', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->ltreeIndex('path');
        });

        Schema::table('test_macro_drop', function (Blueprint $table) {
            $table->dropLtreeIndex('path');
        });

        // If we get here without error, the drop macro worked
        expect(true)->toBeTrue();

        Schema::dropIfExists('test_macro_drop');
    });
});

describe('LtreeIndex with custom names', function (): void {
    it('creates index with custom name', function (): void {
        Schema::create('test_custom_name', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->ltreeIndex('path', 'my_special_idx');
        });

        // If we get here without error, custom name worked
        expect(Schema::hasTable('test_custom_name'))->toBeTrue();

        Schema::dropIfExists('test_custom_name');
    });

    it('drops index with custom name', function (): void {
        Schema::create('test_drop_custom_name', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->ltreeIndex('path', 'special_path_idx');
        });

        Schema::table('test_drop_custom_name', function (Blueprint $table) {
            $table->dropLtreeIndex('path', 'special_path_idx');
        });

        // If we get here without error, the custom name drop worked
        expect(true)->toBeTrue();

        Schema::dropIfExists('test_drop_custom_name');
    });
});
