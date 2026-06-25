<?php

namespace App\Http\Controllers;

use App\Core\Application\DTOs\LoginRequestDTO;
use App\Core\Application\UseCases\LoginUseCase;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected LoginUseCase $loginUseCase
    ) {}

    /**
     * Show the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle login submission.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $dto = new LoginRequestDTO(
            username: $request->validated('username'),
            password: $request->validated('password'),
            remember: $request->boolean('remember')
        );

        $response = $this->loginUseCase->execute($dto);

        if ($response->success) {
            return redirect()->route('dashboard');
        }

        return back()
            ->withInput($request->only('username', 'remember'))
            ->withErrors(['username' => $response->errorMessage]);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
