<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // 👈 Важливий імпорт
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Events\AppointmentCancelled;
use App\Events\ScheduleChanged;
use App\Listeners\ProcessReschedulingQueue;
use App\Listeners\SendWaitlistOffers;
use App\Services\Notifications\LogSmsGateway;
use App\Services\Notifications\SmsGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
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

        Event::listen(ScheduleChanged::class, ProcessReschedulingQueue::class);
        Event::listen(AppointmentCancelled::class, SendWaitlistOffers::class);
    }
}
