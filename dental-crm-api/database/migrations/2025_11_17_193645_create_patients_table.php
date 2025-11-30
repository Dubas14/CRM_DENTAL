<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('full_name');
            $table->date('birth_date')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('address')->nullable();
            $table->text('note')->nullable(); // примітки адміністратора

            $table->timestamps();

            $table->index(['clinic_id', 'full_name']);
            $table->index('phone');
            $table->index('email');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
