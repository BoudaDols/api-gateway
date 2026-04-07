<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceProxyTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return app(JWTService::class)->generateToken([
            'email' => $user->email,
            'name'  => $user->name,
            'role'  => $user->role,
        ]);
    }

    public function test_proxy_returns_404_for_unknown_service(): void
    {
        $user  = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/services/unknown-service/path', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_proxy_requires_authentication(): void
    {
        $response = $this->getJson('/api/services/orders/123');
        $response->assertStatus(401);
    }

    public function test_proxy_forwards_to_microservice(): void
    {
        config(['gateway.services.orders' => 'http://order-service']);

        Http::fake([
            'http://order-service/*' => Http::response(['id' => 123], 200),
        ]);

        $user  = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/services/orders/123', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);
    }

    public function test_proxy_returns_502_when_service_is_down(): void
    {
        config(['gateway.services.orders' => 'http://order-service']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('refused');
        });

        $user  = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/services/orders/123', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(502);
    }

    public function test_proxy_forwards_user_context_headers(): void
    {
        config(['gateway.services.orders' => 'http://order-service']);

        Http::fake([
            'http://order-service/*' => Http::response([], 200),
        ]);

        $user  = User::factory()->create(['role' => 'admin']);
        $token = $this->tokenFor($user);

        $this->getJson('/api/services/orders/123', [
            'Authorization' => "Bearer $token",
        ]);

        Http::assertSent(function ($request) use ($user) {
            return $request->hasHeader('X-User-Email', $user->email) &&
                   $request->hasHeader('X-User-Role', 'admin');
        });
    }
}
