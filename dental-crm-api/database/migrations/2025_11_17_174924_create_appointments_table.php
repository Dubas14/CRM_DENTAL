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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained()
                ->cascadeOnDelete();

            // patient_id додамо FK пізніше, коли зробимо таблицю patients
            $table->unsignedBigInteger('patient_id')->nullable();

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            $table->string('status')->default('planned');
            // planned, confirmed, in_progress, completed, cancelled, no_show

            $table->string('source')->nullable();   // phone/site/in_person
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
