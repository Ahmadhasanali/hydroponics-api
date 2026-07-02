<?php

namespace Tests\Feature\FarmMember;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FarmMemberTest extends TestCase
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

    /** @see FR #4 - Farm Members: View members */
    public function test_owner_can_view_members(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($owner)->get(route('farm.members.index', $farm));

        $response->assertOk();
        $response->assertSee($owner->name);
    }

    /** @see FR #4 - Farm Members: Invite member */
    public function test_owner_can_invite_member_by_email(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $invitee = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('farm.members.store', $farm), [
            'email' => $invitee->name,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('farm.members.index', $farm));
        $this->assertDatabaseHas('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
    }

    /** @see FR #4 - Farm Members: Cannot invite non-existent email */
    public function test_owner_cannot_invite_nonexistent_name(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();

        $response = $this->actingAs($owner)->post(route('farm.members.store', $farm), [
            'email' => 'nonexistent_user',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /** @see FR #4 - Farm Members: Remove member */
    public function test_owner_can_remove_member(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);
        $farmUser = FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $member->id)
            ->first();

        $response = $this->actingAs($owner)->delete(route('farm.members.destroy', [$farm, $farmUser]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('farm_users', [
            'farm_id' => $farm->id,
            'user_id' => $member->id,
        ]);
    }

    /** @see FR #4 - Farm Members: Owner cannot remove self */
    public function test_owner_cannot_remove_self(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->setUpFarm();
        $farmUser = FarmUser::where('farm_id', $farm->id)
            ->where('user_id', $owner->id)
            ->first();

        $response = $this->actingAs($owner)->delete(route('farm.members.destroy', [$farm, $farmUser]));

        $response->assertSessionHasErrors('error');
    }

    /** @see FR #4 - Farm Members: Member cannot invite */
    public function test_member_cannot_invite(): void
    {
        ['farm' => $farm] = $this->setUpFarm();
        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->post(route('farm.members.store', $farm), [
            'email' => 'test@example.com',
        ]);

        $response->assertForbidden();
    }
}
