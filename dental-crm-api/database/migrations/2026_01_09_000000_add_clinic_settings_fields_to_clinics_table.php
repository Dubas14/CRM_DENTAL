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
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('website');
            $table->string('phone_main')->nullable()->after('phone');
            $table->string('email_public')->nullable()->after('email');
            $table->string('address_street')->nullable()->after('address');
            $table->string('address_building')->nullable()->after('address_street');
            $table->string('slogan')->nullable()->after('legal_name');
            $table->string('currency_code')->default('UAH')->after('slogan');
            $table->json('requisites')->nullable()->after('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'logo_url',
                'phone_main',
                'email_public',
                'address_street',
                'address_building',
                'slogan',
                'currency_code',
                'requisites',
            ]);
        });
    }
};
