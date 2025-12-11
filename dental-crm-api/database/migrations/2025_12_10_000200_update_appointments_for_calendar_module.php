<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->after('doctor_id')->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->after('procedure_id')->constrained()->nullOnDelete();
            $table->boolean('is_follow_up')->default(false)->after('patient_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('is_follow_up');
            $table->dropForeign(['procedure_id']);
            $table->dropForeign(['room_id']);
            $table->dropColumn(['procedure_id', 'room_id']);
        });
    }
};
