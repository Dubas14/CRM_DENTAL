<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->string('unit', 20); // pcs, ml, g, pack
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->decimal('min_stock_level', 10, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'name']);
            $table->index(['clinic_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
