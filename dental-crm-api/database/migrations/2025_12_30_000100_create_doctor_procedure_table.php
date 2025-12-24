<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_procedure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('custom_duration_minutes')->nullable();
            $table->unique(['doctor_id', 'procedure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_procedure');
    }
};
