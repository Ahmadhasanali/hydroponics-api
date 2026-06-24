<?php

namespace App\Core\Application\UseCases;

use App\Core\Application\DTOs\LoginRequestDTO;
use App\Core\Application\DTOs\LoginResponseDTO;
use App\Core\Domain\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class LoginUseCase
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Execute the login use case.
     *
     * @param LoginRequestDTO $request
     * @return LoginResponseDTO
     */
    public function execute(LoginRequestDTO $request): LoginResponseDTO
    {
        // 1. Business Logic / Check if User exists (Domain rule check via Repository)
        $user = $this->userRepository->findByName($request->username);

        if (!$user) {
            return new LoginResponseDTO(false, 'Username atau password salah.');
        }

        // 2. Perform authentication attempt
        $credentials = [
            'name' => $request->username,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->remember)) {
            // Regenerate session to prevent session fixation attacks
            request()->session()->regenerate();
            return new LoginResponseDTO(true);
        }

        return new LoginResponseDTO(false, 'Username atau password salah.');
    }
}
