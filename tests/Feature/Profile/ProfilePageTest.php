<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_auth(): void
    {
        $this->get(route('profile'))->assertRedirect('/login');
    }

    public function test_profile_page_shows_user_info_and_secondary_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('Profil')
            ->assertSee($user->name)
            ->assertSee(route('tank.index'));
    }
}
