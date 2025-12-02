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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained(); // Хто лікував

            // Прив'язка до візиту (необов'язкова, бо запис може бути створений без календаря)
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            // Якщо лікували конкретний зуб (або декілька, можна зберігати як JSON або окрему таблицю)
            // Для спрощення почнемо з одного поля, або null якщо це загальний огляд
            $table->integer('tooth_number')->nullable();

            $table->string('diagnosis')->nullable(); // Наприклад: "K02.1 Карієс дентину"
            $table->text('complaints')->nullable();  // Скарги
            $table->text('treatment')->nullable();   // Опис лікування ("Проведено анестезію...")

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
