<?php

namespace App\Core\Domain\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Find a user by their name (username).
     *
     * @param string $name
     * @return User|null
     */
    public function findByName(string $name): ?User;
}
