<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // 👈 Важливий імпорт
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🔥 МАГІЯ ТУТ:
        // Перед будь-якою перевіркою прав (Policy) запускається цей код.
        // Якщо юзер має роль super_admin, ми дозволяємо все (return true).

        Gate::before(function (User $user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });
    }
}
