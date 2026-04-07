<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ServiceProxyService
{
    public function forward(Request $request, string $serviceUrl, string $path): Response
    {
        $targetUrl = rtrim($serviceUrl, '/') . '/' . ltrim($path, '/');

        // Append query string if present
        if ($request->getQueryString()) {
            $targetUrl .= '?' . $request->getQueryString();
        }

        // User context headers — microservices trust these instead of validating JWT
        $headers = [
            'X-User-Email' => $request->input('user_email'),
            'X-User-Name'  => $request->input('user_name'),
            'X-User-Role'  => $request->input('user_role'),
            'Accept'       => 'application/json',
        ];

        try {
            $response = Http::timeout(config('gateway.timeout'))
                ->withHeaders($headers)
                ->send($request->method(), $targetUrl, [
                    'json' => $request->isJson() ? $request->json()->all() : null,
                ]);

            return response($response->body(), $response->status())
                ->header('Content-Type', $response->header('Content-Type') ?? 'application/json');

        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'Service unavailable. Please try again later.',
            ], 502);
        }
    }
}
