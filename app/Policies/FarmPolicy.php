<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    public function view(User $user, Farm $farm): bool
    {
        return $farm->users()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Farm $farm): bool
    {
        return $farm->users()
            ->where('user_id', $user->id)
            ->wherePivotIn('role', ['owner', 'manager'])
            ->exists();
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $farm->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function manageMembers(User $user, Farm $farm): bool
    {
        return $this->update($user, $farm);
    }

    public function manageStaff(User $user, Farm $farm): bool
    {
        return $this->update($user, $farm);
    }

    public function transferOwnership(User $user, Farm $farm): bool
    {
        return $this->delete($user, $farm);
    }
}
