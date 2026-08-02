<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Lupa Kata Sandi');
    }

    public function test_password_reset_link_is_sent_for_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ali@mail.local']);

        $response = $this->post(route('password.email'), ['email' => 'ali@mail.local']);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, fn (ResetPassword $notification) => true);
    }

    public function test_password_reset_link_shows_same_status_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@mail.local']);

        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $response->assertOk();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::attempt(['email' => 'ali@mail.local', 'password' => 'new-password']));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ali@mail.local']);
    }

    public function test_password_can_not_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse(Auth::attempt(['email' => 'ali@mail.local', 'password' => 'new-password']));
    }

    public function test_password_can_not_be_reset_with_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@mail.local',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);
        DB::table('password_reset_tokens')
            ->where('email', 'ali@mail.local')
            ->update(['created_at' => now()->subMinutes(61)]);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ali@mail.local',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_requires_email_and_password(): void
    {
        $response = $this->post(route('password.store'), [
            'token' => 'token',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }
}
