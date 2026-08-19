<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\RegistrationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('api/v1/login', [AuthController::class, 'login']);
        Route::post('api/v1/register', [RegistrationController::class, 'register']);
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify');
    }

    private function credentials(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
        ];
    }

    #[Test]
    public function register_returns_201_with_user_and_token(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token'],
                'message',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'dewi@example.com']);
    }

    #[Test]
    public function register_rejects_unconfirmed_password(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function login_with_wrong_credentials_returns_422_with_errors(): void
    {
        User::factory()->create([
            'email' => 'dewi@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'dewi@example.com',
            'password' => 'wrong-password',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function login_with_unverified_email_returns_403(): void
    {
        User::factory()->unverified()->create([
            'email' => 'dewi@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/login', $this->credentials(User::query()->where('email', 'dewi@example.com')->first()));

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Email belum diverifikasi. Silakan cek inbox Anda.');
    }

    #[Test]
    public function login_with_verified_email_returns_user_and_token(): void
    {
        $user = User::factory()->create([
            'email' => 'dewi@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->markEmailAsVerified();

        $response = $this->postJson('/api/v1/login', $this->credentials($user));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token'],
                'message',
            ]);
    }
}
