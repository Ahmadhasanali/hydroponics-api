<?php

namespace Tests\Feature\Staff;

use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffNutrientApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Farm $farm;

    private Farm $otherFarm;

    private Staff $staff;

    private Tank $tank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->farm = Farm::factory()->create();
        $this->otherFarm = Farm::factory()->create();
        $this->staff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $this->tank = Tank::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Tank A', 'created_by' => $this->farm->created_by]);
        Sanctum::actingAs($this->staff, ['staff']);
    }

    public function test_staff_can_list_own_nutrients(): void
    {
        NutrientAddition::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);
        NutrientAddition::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-02']);

        $this->getJson('/api/v1/staff/nutrients')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_can_store_nutrient(): void
    {
        $response = $this->postJson('/api/v1/staff/nutrients', [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm_before' => 600,
            'ppm_after' => 900,
            'nutrient_a_ml' => 50,
            'nutrient_b_ml' => 50,
            'notes' => 'AB Mix pagi',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nutrient.staff_id', $this->staff->id);
    }

    public function test_staff_cannot_store_for_tank_of_another_farm(): void
    {
        $otherTank = Tank::factory()->create(['farm_id' => $this->otherFarm->id, 'name' => 'Tank Lain', 'created_by' => $this->otherFarm->created_by]);

        $this->postJson('/api/v1/staff/nutrients', [
            'tank_id' => $otherTank->id,
            'log_date' => '2026-08-01',
            'ppm_before' => 600,
            'ppm_after' => 900,
            'nutrient_a_ml' => 50,
            'nutrient_b_ml' => 50,
        ])->assertStatus(403);
    }

    public function test_staff_can_update_own_nutrient(): void
    {
        $nutrient = NutrientAddition::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/nutrients/{$nutrient->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm_before' => 600,
            'ppm_after' => 950,
            'nutrient_a_ml' => 55,
            'nutrient_b_ml' => 55,
        ])->assertOk()->assertJsonPath('data.nutrient.ppm_after', '950.00');
    }

    public function test_staff_cannot_update_nutrient_of_another_staff(): void
    {
        $otherStaff = Staff::factory()->create(['farm_id' => $this->farm->id]);
        $nutrient = NutrientAddition::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $otherStaff->id, 'log_date' => '2026-08-01']);

        $this->patchJson("/api/v1/staff/nutrients/{$nutrient->id}", [
            'tank_id' => $this->tank->id,
            'log_date' => '2026-08-01',
            'ppm_before' => 600,
            'ppm_after' => 950,
            'nutrient_a_ml' => 55,
            'nutrient_b_ml' => 55,
        ])->assertStatus(403);
    }

    public function test_staff_can_delete_own_nutrient(): void
    {
        $nutrient = NutrientAddition::factory()->create(['tank_id' => $this->tank->id, 'staff_id' => $this->staff->id, 'log_date' => '2026-08-01']);

        $this->deleteJson("/api/v1/staff/nutrients/{$nutrient->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('nutrient_additions', ['id' => $nutrient->id]);
    }
}
