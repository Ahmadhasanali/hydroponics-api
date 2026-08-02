<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_admin_can_store_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'is_admin' => $user->is_admin,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('user.store'), [
            'name' => 'aliusername',
            'email' => 'ali@mail.local',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'aliusername',
            'email' => 'ali@mail.local',
        ]);

        $response->assertRedirect(route('user.index'));
        $response->assertSessionHas('password');
        $this->assertNotNull(session('password'));
    }

    public function test_admin_store_user_requires_email(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user);

        $response = $this->post(route('user.store'), [
            'name' => 'aliusername',
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_admin_store_user_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@mail.local']);
        $user = User::factory()->admin()->create();

        $this->actingAs($user);

        $response = $this->post(route('user.store'), [
            'name' => 'aliusername',
            'email' => 'existing@mail.local',
        ]);

        $response->assertInvalid(['email']);
    }

    public function test_user_can_not_store_another_user(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'is_admin' => $user->is_admin,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('user.store'), [
            'name' => 'aliusername',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', [
            'name' => 'aliusername',
        ]);
    }

    public function test_admin_store_user_with_invalid_name(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'is_admin' => $user->is_admin,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('user.store'), [
            'name' => '',
        ]);

        $response->assertInvalid();
        $response->assertSessionHasErrors('name');
    }

    public function test_admin_delete_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'is_admin' => $user->is_admin,
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('user.destroy', $user->id));

        $response->assertRedirect(route('user.index'));
        $this->assertSoftDeleted($user);
    }

    public function test_admin_store_creates_verified_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('user.store'), [
            'name' => 'Petani Baru',
            'email' => 'petani@example.com',
        ]);

        $response->assertRedirect(route('user.index'));
        $this->assertNotNull(User::where('email', 'petani@example.com')->first()->email_verified_at);
    }

    public function test_factory_has_verified_and_unverified_states(): void
    {
        $this->assertNotNull(User::factory()->create()->email_verified_at);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);
    }
}
