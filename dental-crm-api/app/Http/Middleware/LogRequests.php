<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        if (config('app.log_requests', false) && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            try {
                Log::info('Incoming request', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                ]);
            } catch (\Exception $e) {
                // Silently continue if logging fails
            }
        }

        return $response;
    }
}

