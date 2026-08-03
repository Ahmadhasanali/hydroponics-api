<?php

namespace Tests\Feature\FarmMember;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmMemberRoleTest extends TestCase
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

    public function test_invited_member_gets_manager_role(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $invitee = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('farm.members.store', $farm), [
            'email' => $invitee->name,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $invitee->id,
            'role' => 'manager',
        ]);
    }

    public function test_manager_can_invite_member(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $invitee = User::factory()->create();

        $response = $this->actingAs($manager)->post(route('farm.members.store', $farm), [
            'email' => $invitee->name,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $invitee->id,
            'role' => 'manager',
        ]);
    }

    public function test_manager_cannot_remove_owner(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $ownerFarmUser = FarmUser::where('farm_id', $farm->id)->where('user_id', $owner->id)->first();

        $response = $this->actingAs($manager)->delete(route('farm.members.destroy', [$farm, $ownerFarmUser]));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('farm_users', ['farm_id' => $farm->id, 'user_id' => $owner->id]);
    }

    public function test_owner_can_remove_manager(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $managerFarmUser = FarmUser::where('farm_id', $farm->id)->where('user_id', $manager->id)->first();

        $response = $this->actingAs($owner)->delete(route('farm.members.destroy', [$farm, $managerFarmUser]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $manager->id,
        ]);
    }
}
