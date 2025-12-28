<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reschedule_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('doctor_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('appointment_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->dateTime('old_start_at');
            $table->dateTime('old_end_at')->nullable();
            $table->json('suggested_slots')->nullable();
            $table->enum('status', ['pending', 'notified', 'done', 'cancelled'])
                ->default('pending');
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'status']);
            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reschedule_candidates');
    }
};
