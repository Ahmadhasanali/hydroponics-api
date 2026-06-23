<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_user_model_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user);
    }
}
