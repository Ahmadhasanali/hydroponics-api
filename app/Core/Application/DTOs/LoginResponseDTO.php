<?php

namespace App\Core\Application\DTOs;

class LoginResponseDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null
    ) {}
}
