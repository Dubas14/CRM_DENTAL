<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_room', function (Blueprint $table) {
            $table->foreignId('procedure_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('room_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['procedure_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_room');
    }
};
