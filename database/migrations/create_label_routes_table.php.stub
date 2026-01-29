<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('label-tree.tables.routes', 'label_routes'), function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('path', 1000)->unique();
            $table->integer('depth')->default(0);
            $table->timestamps();

            $table->index('path');
            $table->index('depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('label-tree.tables.routes', 'label_routes'));
    }
};
