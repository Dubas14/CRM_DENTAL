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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained()
                ->cascadeOnDelete();

            // 1 = Monday ... 7 = Sunday
            $table->tinyInteger('weekday');

            $table->time('start_time'); // початок прийому
            $table->time('end_time');   // кінець

            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();

            $table->unsignedInteger('slot_duration_minutes')->default(30);

            $table->timestamps();

            $table->unique(['doctor_id', 'weekday']); // 1 запис на день
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
