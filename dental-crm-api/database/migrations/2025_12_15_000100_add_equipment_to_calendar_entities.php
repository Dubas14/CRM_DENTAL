<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('default_room_id')->constrained('equipments')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('room_id')->constrained('equipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });

        Schema::table('procedures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};
