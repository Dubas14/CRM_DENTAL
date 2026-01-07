<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_tooth_statuses', function (Blueprint $table) {
            $table->jsonb('surfaces')->nullable()->after('tooth_number');
        });
    }

    public function down(): void
    {
        Schema::table('patient_tooth_statuses', function (Blueprint $table) {
            $table->dropColumn('surfaces');
        });
    }
};
