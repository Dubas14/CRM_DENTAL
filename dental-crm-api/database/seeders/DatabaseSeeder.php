<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Створюємо Супер Адміна
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.ua',
            'password' => Hash::make('admin'), // Пароль: password
            'is_admin' => true,
        ]);

        // Для тесту можна створити ще 5 звичайних менеджерів (опціонально)
        // User::factory(5)->create();
    }
}
