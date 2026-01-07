<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('doctor_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['doctor_id', 'weekday']);
        });

        // Заповнюємо clinic_id поточним doctor->clinic_id
        DB::statement('UPDATE schedules s SET clinic_id = (SELECT clinic_id FROM doctors d WHERE d.id = s.doctor_id)');

        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['doctor_id', 'clinic_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique(['doctor_id', 'clinic_id', 'weekday']);
            $table->dropConstrainedForeignId('clinic_id');
            $table->unique(['doctor_id', 'weekday']);
        });
    }
};
