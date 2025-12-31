<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // 👈 Важливий імпорт
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        // Rate limiting configuration
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user?->id ?: $request->ip();

            $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

            // SPA робить багато читань при навігації між модулями, тому ліміти мають бути значно вищі.
            // Write-операції залишаємо більш консервативними.
            if ($user) {
                return $isRead
                    ? Limit::perMinute(600)->by($key)
                    : Limit::perMinute(180)->by($key);
            }

            return $isRead
                ? Limit::perMinute(120)->by($key)
                : Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Забагато спроб входу. Спробуйте через хвилину.',
                        'error' => 'rate_limit_exceeded',
                    ], 429);
                });
        });

        RateLimiter::for('read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
