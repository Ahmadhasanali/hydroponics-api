<?php

namespace Tests\Unit\Services;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\User;
use App\Services\ReminderTargetResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderTargetResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeFarmWithRoles(array $roles): array
    {
        $farm = Farm::factory()->create();
        $members = [];

        foreach ($roles as $key => $role) {
            $user = User::factory()->create();
            $farm->users()->attach($user->id, ['role' => $role]);
            $members[$key] = $user;
        }

        $staff = Staff::factory()->create(['farm_id' => $farm->id]);
        $members['staff'] = $staff;

        return ['farm' => $farm, ...$members];
    }

    public function test_level_of_user_and_staff(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertSame(2, $resolver->levelOf($owner, $farm));
        $this->assertSame(1, $resolver->levelOf($manager, $farm));
        $this->assertSame(0, $resolver->levelOf($staff, $farm));
    }

    public function test_owner_can_target_everyone_in_farm(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertTrue($resolver->canTarget($owner, $farm, $owner));
        $this->assertTrue($resolver->canTarget($owner, $farm, $manager));
        $this->assertTrue($resolver->canTarget($owner, $farm, $staff));
    }

    public function test_manager_cannot_target_owner(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $this->assertFalse($resolver->canTarget($manager, $farm, $owner));
        $this->assertTrue($resolver->canTarget($manager, $farm, $manager));
    }

    public function test_staff_can_only_target_other_staff_in_same_farm(): void
    {
        ['farm' => $farm, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'manager' => 'manager',
        ]);
        $otherStaff = Staff::factory()->create(['farm_id' => $farm->id]);
        $otherFarmStaff = Staff::factory()->create(['farm_id' => Farm::factory()->create()->id]);

        $resolver = new ReminderTargetResolver;

        $this->assertTrue($resolver->canTarget($staff, $farm, $staff));
        $this->assertTrue($resolver->canTarget($staff, $farm, $otherStaff));
        $this->assertFalse($resolver->canTarget($staff, $farm, $manager));
        $this->assertFalse($resolver->canTarget($staff, $farm, $otherFarmStaff));
    }

    public function test_resolve_all_includes_everyone_in_farm(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;
        $targets = $resolver->resolveTargets($owner, $farm, 'all');

        $this->assertCount(3, $targets);

        $flattened = array_map(
            fn (array $t): string => $t['type'].':'.$t['id'],
            $targets,
        );

        $this->assertContains(User::class.':'.$owner->id, $flattened);
        $this->assertContains(User::class.':'.$manager->id, $flattened);
        $this->assertContains(Staff::class.':'.$staff->id, $flattened);
    }

    public function test_resolve_specific_filters_by_hierarchy(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager, 'staff' => $staff] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        // manager mencoba target owner → ditolak
        $targets = $resolver->resolveTargets($manager, $farm, 'specific', [User::class.':'.$owner->id]);

        $this->assertSame([], $targets);
    }

    public function test_resolve_specific_skips_malformed_target_ids(): void
    {
        ['farm' => $farm, 'owner' => $owner, 'manager' => $manager] = $this->makeFarmWithRoles([
            'owner' => 'owner',
            'manager' => 'manager',
        ]);

        $resolver = new ReminderTargetResolver;

        $targets = $resolver->resolveTargets($manager, $farm, 'specific', [
            'garbage',
            User::class.':'.$owner->id.':::x',
        ]);

        $this->assertSame([], $targets);
    }
}
