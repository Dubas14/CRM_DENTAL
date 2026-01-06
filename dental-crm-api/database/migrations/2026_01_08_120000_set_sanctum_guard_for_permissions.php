<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Приберемо дублікати (коли вже є такий name з guard sanctum)
        DB::table('roles')
            ->where('guard_name', '!=', 'sanctum')
            ->whereIn('name', function ($q) {
                $q->select('name')->from('roles')->where('guard_name', 'sanctum');
            })
            ->delete();

        DB::table('permissions')
            ->where('guard_name', '!=', 'sanctum')
            ->whereIn('name', function ($q) {
                $q->select('name')->from('permissions')->where('guard_name', 'sanctum');
            })
            ->delete();

        // 2) Переводимо на guard 'sanctum' тільки там, де ще не sanctum
        DB::table('roles')
            ->where('guard_name', '!=', 'sanctum')
            ->update(['guard_name' => 'sanctum']);

        DB::table('permissions')
            ->where('guard_name', '!=', 'sanctum')
            ->update(['guard_name' => 'sanctum']);
    }

    public function down(): void
    {
        // Повертаємо на web (якщо потрібно)
        DB::table('roles')
            ->where('guard_name', '!=', 'web')
            ->update(['guard_name' => 'web']);

        DB::table('permissions')
            ->where('guard_name', '!=', 'web')
            ->update(['guard_name' => 'web']);
    }
};

