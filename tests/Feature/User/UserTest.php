<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'aliusername',
        ]);

        $response->assertRedirect(route('user.index'));
        $response->assertSessionHas('password');
        $password = session('password');

        $this->assertNotNull($password);
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
        $this->assertSoftDeleted('users', $user);
    }
}
