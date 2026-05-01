<?php

namespace Tests\Unit;

use App\Services\ServiceProxyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceProxyServiceTest extends TestCase
{
    private ServiceProxyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ServiceProxyService;
    }

    public function test_forwards_request_and_returns_microservice_response(): void
    {
        Http::fake([
            'http://order-service/*' => Http::response(['order' => 123], 200),
        ]);

        $request = Request::create('/api/services/orders/123', 'GET');
        $request->merge(['user_email' => 'test@example.com', 'user_name' => 'Test', 'user_role' => 'user']);

        $response = $this->service->forward($request, 'http://order-service', '123');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_returns_502_when_microservice_is_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $request = Request::create('/api/services/orders/123', 'GET');
        $request->merge(['user_email' => 'test@example.com', 'user_name' => 'Test', 'user_role' => 'user']);

        $response = $this->service->forward($request, 'http://order-service', '123');

        $this->assertEquals(502, $response->getStatusCode());
        $this->assertStringContainsString('unavailable', $response->getContent());
    }

    public function test_forwards_query_string(): void
    {
        Http::fake([
            'http://order-service/*' => Http::response([], 200),
        ]);

        $request = Request::create('/api/services/orders/list?page=2&limit=10', 'GET');
        $request->merge(['user_email' => 'test@example.com', 'user_name' => 'Test', 'user_role' => 'user']);

        $response = $this->service->forward($request, 'http://order-service', 'list');

        Http::assertSent(function ($httpRequest) {
            return str_contains($httpRequest->url(), 'page=2') &&
                   str_contains($httpRequest->url(), 'limit=10');
        });
    }

    public function test_forwards_user_context_headers(): void
    {
        Http::fake([
            'http://order-service/*' => Http::response([], 200),
        ]);

        $request = Request::create('/api/services/orders/123', 'GET');
        $request->merge(['user_email' => 'john@example.com', 'user_name' => 'John', 'user_role' => 'admin']);

        $this->service->forward($request, 'http://order-service', '123');

        Http::assertSent(function ($httpRequest) {
            return $httpRequest->hasHeader('X-User-Email', 'john@example.com') &&
                   $httpRequest->hasHeader('X-User-Name', 'John') &&
                   $httpRequest->hasHeader('X-User-Role', 'admin');
        });
    }
}
