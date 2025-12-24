<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('procedure_step_id')
                ->nullable()
                ->after('procedure_id')
                ->constrained('procedure_steps')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['procedure_step_id']);
            $table->dropColumn('procedure_step_id');
        });
    }
};
