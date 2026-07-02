<?php

namespace Tests\Feature\NutrientAddition;

use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NutrientAdditionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        return compact('user', 'farm', 'tank');
    }

    /** @see FR #7 - Nutrient Addition: View history */
    public function test_user_can_view_nutrient_list(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        NutrientAddition::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('nutrient-addition.index'));

        $response->assertOk();
    }

    /** @see FR #7 - Nutrient Addition: Create log */
    public function test_user_can_create_nutrient_addition(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('nutrient-addition.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm_before' => 700,
            'ppm_after' => 900,
            'nutrient_a_ml' => 50,
            'nutrient_b_ml' => 50,
        ]);

        $response->assertRedirect(route('nutrient-addition.index'));
        $this->assertDatabaseHas('nutrient_additions', ['tank_id' => $tank->id, 'ppm_before' => 700]);
    }

    /** @see FR #7 - Nutrient Addition: PPM After > PPM Before */
    public function test_nutrient_ppm_after_must_be_greater(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();

        $response = $this->actingAs($user)->post(route('nutrient-addition.store'), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm_before' => 900,
            'ppm_after' => 700,
        ]);

        $response->assertSessionHasErrors('ppm_after');
    }

    /** @see FR #7 - Nutrient Addition: Edit log */
    public function test_user_can_update_nutrient_addition(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $addition = NutrientAddition::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('nutrient-addition.update', $addition), [
            'tank_id' => $tank->id,
            'log_date' => now()->toDateString(),
            'ppm_before' => 600,
            'ppm_after' => 1000,
            'nutrient_a_ml' => 100,
            'nutrient_b_ml' => 100,
        ]);

        $response->assertRedirect(route('nutrient-addition.index'));
        $this->assertDatabaseHas('nutrient_additions', ['id' => $addition->id, 'ppm_before' => 600]);
    }

    /** @see FR #7 - Nutrient Addition: Delete log */
    public function test_user_can_delete_nutrient_addition(): void
    {
        ['user' => $user, 'tank' => $tank] = $this->setUpFarm();
        $addition = NutrientAddition::factory()->create(['tank_id' => $tank->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('nutrient-addition.destroy', $addition));

        $response->assertRedirect(route('nutrient-addition.index'));
        $this->assertSoftDeleted($addition);
    }
}
