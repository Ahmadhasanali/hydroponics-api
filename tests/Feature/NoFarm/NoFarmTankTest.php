<?php

namespace Tests\Feature\NoFarm;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NoFarmTankTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_no_farm_user_gets_empty_state_on_tank_index_not_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tank.index'));

        $response->assertOk();
        $response->assertSee('Belum Ada Farm');
        $response->assertSee('Buat Farm Baru');
    }

    public function test_no_farm_user_gets_empty_state_on_tank_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tank.create'));

        $response->assertOk();
        $response->assertSee('Buat Farm Baru');
    }

    public function test_no_farm_user_cannot_store_tank(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tank.store'), [
            'name' => 'Tank A1',
            'capacity_liter' => 100,
        ]);

        $response->assertRedirect(route('farm.create'));
        $this->assertDatabaseCount('tanks', 0);
    }
}
