<?php

namespace Tests\Feature\Reminder;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ReminderAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeFarmWithOwnerAndManager(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        return compact('owner', 'manager', 'farm');
    }

    public function test_creator_can_view_update_delete(): void
    {
        ['owner' => $owner, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $reminder));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $reminder));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $reminder));
    }

    public function test_non_creator_member_cannot_update_or_delete(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);

        $this->assertFalse(Gate::forUser($manager)->allows('update', $reminder));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $reminder));
    }

    public function test_target_can_view_but_not_update(): void
    {
        ['owner' => $owner, 'manager' => $manager, 'farm' => $farm] = $this->makeFarmWithOwnerAndManager();
        $reminder = Reminder::factory()->create([
            'farm_id' => $farm->id,
            'created_by_type' => User::class,
            'created_by_id' => $owner->id,
        ]);
        $reminder->targets()->create([
            'targetable_type' => User::class,
            'targetable_id' => $manager->id,
        ]);

        $this->assertTrue(Gate::forUser($manager)->allows('view', $reminder));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $reminder));
    }
}
