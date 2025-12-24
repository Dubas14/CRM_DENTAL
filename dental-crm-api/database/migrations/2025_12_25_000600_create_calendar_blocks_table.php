<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('room_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('equipment_id')
                ->nullable()
                ->constrained('equipments')
                ->nullOnDelete();

            $table->foreignId('assistant_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('type', [
                'work',
                'vacation',
                'equipment_booking',
                'room_block',
                'personal_block',
            ]);

            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'start_at']);
            $table->index(['doctor_id', 'start_at']);
            $table->index(['room_id', 'start_at']);
            $table->index(['equipment_id', 'start_at']);
            $table->index(['assistant_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');
    }
};
