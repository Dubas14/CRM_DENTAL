<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('waitlist_entry_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'claimed', 'expired', 'failed'])
                ->default('pending');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_offers');
    }
};
