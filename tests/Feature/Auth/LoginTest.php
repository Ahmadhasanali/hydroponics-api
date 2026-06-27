<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Selamat Datang');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'name' => 'aliusername',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'username' => 'aliusername',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'name' => 'aliusername',
            'password' => Hash::make('password123'),
        ]);

        $this->post(route('login'), [
            'username' => 'aliusername',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_unknown_username(): void
    {
        $this->post(route('login'), [
            'username' => 'nobody',
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_login_redirects_back_with_errors_on_failure(): void
    {
        $response = $this->post(route('login'), [
            'username' => 'nobody',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $response->assertRedirect(url()->previous());
    }

    public function test_username_and_password_are_required(): void
    {
        $response = $this->post(route('login'), []);

        $response->assertSessionHasErrors(['username', 'password']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
