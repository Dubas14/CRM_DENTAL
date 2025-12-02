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
        Schema::create('patient_tooth_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // Номер зуба за міжнародною системою ISO (11-48, 51-85 для дітей)
            $table->integer('tooth_number');

            // Статус: healthy, caries, pulpitis, periodontitis, extracted, implant, crown, filled
            $table->string('status')->default('healthy');

            // Додаткові нотатки по зубу (наприклад, "глибокий карієс")
            $table->string('note')->nullable();

            $table->timestamps();

            // У одного пацієнта один запис для кожного конкретного зуба
            $table->unique(['patient_id', 'tooth_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_tooth_statuses');
    }
};
