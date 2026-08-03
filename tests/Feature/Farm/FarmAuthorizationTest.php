<?php

namespace Tests\Feature\Farm;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setUpFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        session()->put('selected_farm_id', $farm->id);

        return compact('owner', 'farm');
    }

    public function test_manager_can_update_farm(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->put(route('farm.update', $farm), [
            'name' => 'Nama Baru',
            'address' => 'Alamat Baru',
            'description' => 'Deskripsi Baru',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Nama Baru']);
    }

    public function test_manager_cannot_delete_farm(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->delete(route('farm.destroy', $farm));

        $response->assertForbidden();
    }

    public function test_owner_can_transfer_ownership_to_manager(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($owner)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $manager->id,
        ]);

        $response->assertRedirect();
        $this->assertSame('owner', FarmUser::where('farm_id', $farm->id)->where('user_id', $manager->id)->first()->role);
        $this->assertSame('manager', FarmUser::where('farm_id', $farm->id)->where('user_id', $owner->id)->first()->role);
    }

    public function test_manager_cannot_transfer_ownership(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $response = $this->actingAs($manager)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $manager->id,
        ]);

        $response->assertForbidden();
    }

    public function test_owner_cannot_transfer_to_self(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($owner)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $owner->id,
        ]);

        $response->assertSessionHasErrors('new_owner_id');
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_non_owner_cannot_transfer(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->post(route('farm.transfer', $farm), [
            'new_owner_id' => $member->id,
        ]);

        $response->assertForbidden();
    }
}
