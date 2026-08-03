<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffNutrientTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpStaff(): array
    {
        $farm = Farm::factory()->create();
        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $tank = Tank::factory()->create(['farm_id' => $farm->id]);

        return compact('farm', 'staff', 'tank');
    }

    public function test_staff_can_create_nutrient_addition(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();

        $response = $this->actingAs($staff, 'staff')->post(route('staff.nutrient.store'), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm_before' => 700,
            'ppm_after' => 900,
            'nutrient_a_ml' => 120,
            'nutrient_b_ml' => 80,
        ]);

        $response->assertRedirect(route('staff.nutrient.index'));
        $this->assertDatabaseHas('nutrient_additions', [
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'ppm_after' => 900,
        ]);
    }

    public function test_staff_cannot_use_tank_of_other_farm(): void
    {
        ['staff' => $staff] = $this->setUpStaff();
        $otherFarm = Farm::factory()->create();
        $otherTank = Tank::factory()->create(['farm_id' => $otherFarm->id]);

        $response = $this->actingAs($staff, 'staff')->post(route('staff.nutrient.store'), [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-03',
            'ppm_before' => 700,
            'ppm_after' => 900,
            'nutrient_a_ml' => 120,
            'nutrient_b_ml' => 80,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_edit_own_nutrient_addition(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $addition = NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
            'log_date' => '2026-08-03',
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.nutrient.update', $addition), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm_before' => 800,
            'ppm_after' => 1000,
            'nutrient_a_ml' => 150,
            'nutrient_b_ml' => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nutrient_additions', ['id' => $addition->id, 'ppm_after' => 1000]);
    }

    public function test_staff_cannot_edit_others_nutrient_addition(): void
    {
        ['farm' => $farm, 'staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $addition = NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $otherStaff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->put(route('staff.nutrient.update', $addition), [
            'tank_id' => $tank->id,
            'log_date' => '2026-08-03',
            'ppm_before' => 800,
            'ppm_after' => 1000,
            'nutrient_a_ml' => 150,
            'nutrient_b_ml' => 100,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_delete_own_nutrient_addition(): void
    {
        ['staff' => $staff, 'tank' => $tank] = $this->setUpStaff();
        $addition = NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'staff_id' => $staff->id,
            'user_id' => null,
        ]);

        $response = $this->actingAs($staff, 'staff')->delete(route('staff.nutrient.destroy', $addition));

        $response->assertRedirect();
        $this->assertSoftDeleted('nutrient_additions', ['id' => $addition->id]);
    }
}
