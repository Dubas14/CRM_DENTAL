<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('bio');
            $table->string('email')->nullable()->after('phone');
            $table->string('room')->nullable()->after('email');
            $table->string('admin_contact')->nullable()->after('room');
            $table->string('address')->nullable()->after('admin_contact');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('zip')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'email',
                'room',
                'admin_contact',
                'address',
                'city',
                'state',
                'zip',
            ]);
        });
    }
};

