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
            $table->string('path', 768)->unique(); // 768 chars * 4 bytes = 3072 bytes (MySQL InnoDB limit)
            $table->integer('depth')->default(0);
            $table->timestamps();

            $table->index('depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('label-tree.tables.routes', 'label_routes'));
    }
};
