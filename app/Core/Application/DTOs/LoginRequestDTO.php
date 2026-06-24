<?php

namespace App\Core\Application\DTOs;

class LoginRequestDTO
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly bool $remember = false
    ) {}
}
