<?php

namespace App\Infrastructure\Repositories;

use App\Core\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;

class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Find a user by their name (username).
     *
     * @param string $name
     * @return User|null
     */
    public function findByName(string $name): ?User
    {
        return User::where('name', $name)->first();
    }
}
