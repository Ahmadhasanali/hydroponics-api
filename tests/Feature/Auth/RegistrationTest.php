<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Daftar');
    }

    public function test_new_user_can_register_and_is_redirected_to_verification(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'petani@example.com',
            'email_verified_at' => null,
        ]);
        Notification::assertSentTo(
            User::where('email', 'petani@example.com')->first(),
            VerifyEmailNotification::class,
        );
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'petani@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_disposable_email(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'user@temp-mail.org',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_confirmed_password(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
            'password' => 'password123',
            'password_confirmation' => 'beda-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_can_register_with_duplicate_name(): void
    {
        Notification::fake();

        User::factory()->create(['name' => 'Petani Baru']);

        $response = $this->post(route('register'), [
            'name' => 'Petani Baru',
            'email' => 'lain@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Petani Baru',
            'email' => 'lain@example.com',
        ]);
    }
}
