<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        if ($this->isCreator($user, $reminder)) {
            return true;
        }

        return $reminder->targets()
            ->where('targetable_type', User::class)
            ->where('targetable_id', $user->id)
            ->exists();
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->isCreator($user, $reminder);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->isCreator($user, $reminder);
    }

    private function isCreator(User $user, Reminder $reminder): bool
    {
        return $reminder->created_by_type === User::class
            && $reminder->created_by_id === $user->id;
    }
}
