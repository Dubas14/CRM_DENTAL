<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Спочатку обробляємо запит
        $response = $next($request);
        
        // Потім логуємо тільки PUT/PATCH/DELETE запити для діагностики
        try {
            if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
                Log::info('Incoming request', [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Exception $e) {
            // Якщо логування не працює, просто продовжуємо
        }

        return $response;
    }
}

