<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        Log::channel('stdout')->info('api_request', [
            'timestamp'   => now()->toIso8601String(),
            'method'      => $request->method(),
            'url'         => $request->path(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startTime) * 1000),
            'ip'          => $request->ip(),
            'user'        => $request->input('user_email'),
        ]);

        return $response;
    }
}
