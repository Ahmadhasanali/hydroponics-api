<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\FinancialCategoryController;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\User;
use Database\Seeders\FinancialCategorySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinancialCategoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FinancialCategorySeeder::class);
        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);

        Route::middleware(SubstituteBindings::class)->group(function (): void {
            Route::apiResource('financial-categories', FinancialCategoryController::class);
        });
    }

    public function test_index_merges_global_and_custom_categories(): void
    {
        FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create(['name' => 'Custom']);

        $response = $this->actingAs($this->owner)
            ->getJson('/financial-categories?farm_id='.$this->farm->id);

        $response->assertOk()->assertJsonPath('success', true);
        $names = collect($response->json('data.categories'))->pluck('name');
        $this->assertTrue($names->contains('Nutrisi (AB Mix)'));
        $this->assertTrue($names->contains('Custom'));
    }

    public function test_store_creates_custom_category_for_farm(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/financial-categories', [
            'farm_id' => $this->farm->id,
            'name' => 'Pakan Ikan',
            'type' => 'expense',
        ]);

        $response->assertCreated()->assertJsonPath('data.category.name', 'Pakan Ikan');
        $this->assertDatabaseHas('financial_categories', ['farm_id' => $this->farm->id, 'name' => 'Pakan Ikan']);
    }

    public function test_store_rejects_duplicate_name_same_type(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/financial-categories', [
            'farm_id' => $this->farm->id,
            'name' => 'Listrik',
            'type' => 'expense',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_update_only_custom_category(): void
    {
        $global = FinancialCategory::whereNull('farm_id')->firstOrFail();
        $custom = FinancialCategory::factory()->forFarm($this->farm->id)->create();

        $this->actingAs($this->owner)
            ->putJson("/financial-categories/{$global->id}", ['name' => 'Baru'])
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->putJson("/financial-categories/{$custom->id}", ['name' => 'Ganti Nama'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Ganti Nama');
    }

    public function test_destroy_deactivates_instead_of_delete(): void
    {
        $custom = FinancialCategory::factory()->forFarm($this->farm->id)->create();

        $this->actingAs($this->owner)
            ->deleteJson("/financial-categories/{$custom->id}")
            ->assertOk();

        $this->assertDatabaseHas('financial_categories', ['id' => $custom->id, 'deleted_at' => null]);
        $this->assertFalse((bool) $custom->fresh()->is_active);
    }

    public function test_member_without_manager_role_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $this->farm->users()->attach($viewer->id, ['role' => 'operator']);

        $this->actingAs($viewer)
            ->postJson('/financial-categories', ['farm_id' => $this->farm->id, 'name' => 'X', 'type' => 'expense'])
            ->assertForbidden();
    }

    public function test_owner_of_other_farm_cannot_update_or_delete_custom_category(): void
    {
        $otherOwner = User::factory()->create();
        $otherFarm = Farm::factory()->create();
        $otherFarm->users()->attach($otherOwner->id, ['role' => 'owner']);
        $custom = FinancialCategory::factory()->forFarm($this->farm->id)->create();

        $this->actingAs($otherOwner)
            ->putJson("/financial-categories/{$custom->id}", ['name' => 'Baru'])
            ->assertForbidden();

        $this->actingAs($otherOwner)
            ->deleteJson("/financial-categories/{$custom->id}")
            ->assertForbidden();

        $this->assertTrue((bool) $custom->fresh()->is_active);
    }

    public function test_non_member_cannot_view_farm_categories(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson('/financial-categories?farm_id='.$this->farm->id)
            ->assertForbidden();
    }

    public function test_destroy_global_category_is_not_found(): void
    {
        $global = FinancialCategory::whereNull('farm_id')->firstOrFail();

        $this->actingAs($this->owner)
            ->deleteJson("/financial-categories/{$global->id}")
            ->assertNotFound();
    }
}
