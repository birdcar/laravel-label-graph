<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $labelsTable = config('label-tree.tables.labels', 'labels');
        $relationshipsTable = config('label-tree.tables.relationships', 'label_relationships');

        Schema::create($relationshipsTable, function (Blueprint $table) use ($labelsTable) {
            $table->ulid('id')->primary();
            $table->ulid('parent_label_id');
            $table->ulid('child_label_id');
            $table->timestamps();

            $table->foreign('parent_label_id')
                ->references('id')
                ->on($labelsTable)
                ->onDelete('cascade');

            $table->foreign('child_label_id')
                ->references('id')
                ->on($labelsTable)
                ->onDelete('cascade');

            $table->unique(['parent_label_id', 'child_label_id']);
            $table->index('parent_label_id');
            $table->index('child_label_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('label-tree.tables.relationships', 'label_relationships'));
    }
};
