<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UnauthenticatedApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_protected_api_route_returns_401_json_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/farms');

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_protected_api_route_returns_401_json_without_accept_header(): void
    {
        $response = $this->get('/api/v1/farms');

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }
}
