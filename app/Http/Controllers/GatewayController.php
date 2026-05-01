<?php

namespace App\Http\Controllers;

use App\Services\ServiceProxyService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayController extends Controller
{
    public function __construct(
        private ServiceProxyService $proxy
    ) {}

    public function proxy(Request $request, string $service, string $path = ''): Response
    {
        $serviceUrl = config("gateway.services.{$service}");

        if (! $serviceUrl) {
            return response()->json([
                'success' => false,
                'message' => "Service '{$service}' not found.",
            ], 404);
        }

        return $this->proxy->forward($request, $serviceUrl, $path);
    }
}
