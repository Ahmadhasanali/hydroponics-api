<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_creates_a_valid_user(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertNotNull($user->id);
        $this->assertFalse($user->is_admin);
        $this->assertNotSame('password', $user->password);
    }

    public function test_user_factory_can_create_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->is_admin);
        $this->assertSame(User::class, $admin::class);
    }
}
