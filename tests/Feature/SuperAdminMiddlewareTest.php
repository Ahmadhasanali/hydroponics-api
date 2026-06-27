<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_are_denied_access_to_super_admin_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get('/super-admin');

        $response->assertForbidden();
    }

    public function test_super_admin_users_can_access_super_admin_routes(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/super-admin');

        $response->assertOk();
        $response->assertSee('Super admin access granted');
    }
}
