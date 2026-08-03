<?php

namespace Tests\Feature\Frontend;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmMenuVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sidebar_hides_farm_menus_for_user_without_farm(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('tank.index').'"', false);
        $response->assertSee('Buat Farm Baru');
    }

    public function test_sidebar_shows_farm_menus_for_user_with_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.route('tank.index').'"', false);
        $response->assertDontSee('Buat Farm Baru');
    }
}
