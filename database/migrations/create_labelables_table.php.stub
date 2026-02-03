<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('label-graph.tables.labelables', 'labelables'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('label_route_id');
            $table->string('labelable_type');
            $table->ulid('labelable_id');
            $table->timestamps();

            $table->foreign('label_route_id')
                ->references('id')
                ->on(config('label-graph.tables.routes', 'label_routes'))
                ->cascadeOnDelete();

            $table->unique(['label_route_id', 'labelable_type', 'labelable_id']);
            $table->index(['labelable_type', 'labelable_id']);
            $table->index('label_route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('label-graph.tables.labelables', 'labelables'));
    }
};
