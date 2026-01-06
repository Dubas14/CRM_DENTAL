<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path', 255);
            $table->string('file_name', 255)->nullable();
            $table->string('file_type', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_files');
    }
};


