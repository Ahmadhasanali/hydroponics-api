<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function register_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Demo Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    #[Test]
    public function password_forgot_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $response = $this->postJson('/api/v1/password/forgot', [
            'email' => 'demo@hydrofarm.id',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function password_reset_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $response = $this->postJson('/api/v1/password/reset', [
            'email' => 'demo@hydrofarm.id',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
            'token' => 'dummy-token',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function telegram_link_code_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/telegram/link-code');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function register_still_works_when_demo_mode_off(): void
    {
        config(['app.demo_mode' => false]);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Normal User',
            'email' => 'normal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'normal@example.com']);
    }

    #[Test]
    public function demo_seeder_creates_verified_demo_user(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => config('app.demo_email', 'demo@hydrofarm.id'),
        ]);
    }
}
