<?php

namespace Tests\Feature\Tank;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TankTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('user', 'farm');
    }

    /** @see FR #5 - Tank Management: View tank */
    public function test_user_can_view_tanks(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('tank.index'));

        $response->assertOk();
        $response->assertSee($tank->name);
    }

    /** @see FR #5 - Tank Management: Create tank */
    public function test_user_can_create_tank(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('tank.store'), [
            'name' => 'Tank A1',
            'capacity_liter' => 100,
            'notes' => 'Tank utama',
        ]);

        $response->assertRedirect(route('tank.index'));
        $this->assertDatabaseHas('tanks', ['name' => 'Tank A1', 'farm_id' => $farm->id]);
    }

    /** @see FR #5 - Tank Management: Unique name within farm */
    public function test_tank_name_must_be_unique_within_farm(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();
        Tank::factory()->create(['farm_id' => $farm->id, 'name' => 'Tank A1']);

        $response = $this->actingAs($user)->post(route('tank.store'), [
            'name' => 'Tank A1',
            'capacity_liter' => 200,
        ]);

        $response->assertSessionHasErrors('name');
    }

    /** @see FR #5 - Tank Management: Edit tank */
    public function test_user_can_update_tank(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id, 'name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('tank.update', $tank), [
            'name' => 'New Name',
            'capacity_liter' => 200,
        ]);

        $response->assertRedirect(route('tank.index'));
        $this->assertDatabaseHas('tanks', ['id' => $tank->id, 'name' => 'New Name']);
    }

    /** @see FR #5 - Tank Management: Delete tank */
    public function test_user_can_delete_tank(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->delete(route('tank.destroy', $tank));

        $response->assertRedirect(route('tank.index'));
        $this->assertSoftDeleted($tank);
    }

    /** @see FR #5 - Tank Management: View tank detail */
    public function test_user_can_view_tank_detail(): void
    {
        ['user' => $user, 'farm' => $farm] = $this->setUpFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('tank.show', $tank));

        $response->assertOk();
        $response->assertSee($tank->name);
    }
}
