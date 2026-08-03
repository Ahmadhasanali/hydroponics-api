<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use Illuminate\Support\Collection;

class ReminderTargetResolver
{
    public const LEVEL_OWNER = 2;

    public const LEVEL_MANAGER = 1;

    public const LEVEL_STAFF = 0;

    public function levelOf(User|Staff $actor, Farm $farm): int
    {
        if ($actor instanceof Staff) {
            return self::LEVEL_STAFF;
        }

        $role = $farm->users()
            ->wherePivot('user_id', $actor->id)
            ->first()
            ?->pivot
            ?->role;

        return match ($role) {
            'owner' => self::LEVEL_OWNER,
            'manager' => self::LEVEL_MANAGER,
            default => self::LEVEL_STAFF,
        };
    }

    public function canTarget(User|Staff $actor, Farm $farm, User|Staff $candidate): bool
    {
        if (! $this->isInFarm($candidate, $farm)) {
            return false;
        }

        return $this->levelOf($candidate, $farm) <= $this->levelOf($actor, $farm);
    }

    /**
     * @param  list<string>  $targetIds  Format: "App\Models\User:123"
     * @return array<int, array{type: class-string, id: int}>
     */
    public function resolveTargets(User|Staff $actor, Farm $farm, string $mode, array $targetIds = []): array
    {
        return match ($mode) {
            'self' => [
                ['type' => $actor::class, 'id' => $actor->id],
            ],
            'all' => $this->resolveAll($actor, $farm),
            'specific' => $this->resolveSpecific($actor, $farm, $targetIds),
            default => [],
        };
    }

    /**
     * @return Collection<int, int>
     */
    public function visibleReminderIds(User|Staff $actor): Collection
    {
        $created = Reminder::query()
            ->where('created_by_type', $actor::class)
            ->where('created_by_id', $actor->id)
            ->pluck('id');

        $targeted = ReminderTarget::query()
            ->where('targetable_type', $actor::class)
            ->where('targetable_id', $actor->id)
            ->pluck('reminder_id');

        return $created->concat($targeted)->unique()->values();
    }

    private function isInFarm(User|Staff $candidate, Farm $farm): bool
    {
        if ($candidate instanceof Staff) {
            return $candidate->farm_id === $farm->id;
        }

        return $farm->users()->where('user_id', $candidate->id)->exists();
    }

    /**
     * @return array<int, array{type: class-string, id: int}>
     */
    private function resolveAll(User|Staff $actor, Farm $farm): array
    {
        $targets = [];

        $farm->users()->get()->each(function (User $user) use (&$targets, $actor, $farm) {
            if ($this->canTarget($actor, $farm, $user)) {
                $targets[] = ['type' => $user::class, 'id' => $user->id];
            }
        });

        $farm->staff()->get()->each(function (Staff $staff) use (&$targets, $actor, $farm) {
            if ($this->canTarget($actor, $farm, $staff)) {
                $targets[] = ['type' => $staff::class, 'id' => $staff->id];
            }
        });

        return $targets;
    }

    /**
     * @param  list<string>  $targetIds
     * @return array<int, array{type: class-string, id: int}>
     */
    private function resolveSpecific(User|Staff $actor, Farm $farm, array $targetIds): array
    {
        $targets = [];

        foreach ($targetIds as $targetId) {
            $parts = explode(':', $targetId, 2);

            if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
                continue;
            }

            [$type, $id] = $parts;
            $id = (int) $id;

            $candidate = match ($type) {
                Staff::class => Staff::query()->find($id),
                User::class => User::query()->find($id),
                default => null,
            };

            if ($candidate && $this->canTarget($actor, $farm, $candidate)) {
                $targets[] = ['type' => $candidate::class, 'id' => $candidate->id];
            }
        }

        return $targets;
    }
}
