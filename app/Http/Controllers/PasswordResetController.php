<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Show the form to request a password reset link.
     */
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the given email address.
     */
    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! $user->is_admin) {
            Password::broker()->sendResetLink($request->only('email'));
        }

        return back()->with('status', __('Jika email terdaftar, kami telah mengirim link reset password ke email Anda.'));
    }

    /**
     * Show the form to reset the password for the given token.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user !== null && $user->is_admin) {
            return back()->withErrors(['email' => __('Link reset password tidak valid atau sudah kedaluwarsa.')]);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password): void {
                $user->password = $password;
                $user->save();
                event(new PasswordReset($user));
                Auth::login($user);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('dashboard')->with('status', __('Password berhasil direset.'))
            : back()->withErrors(['email' => __('Link reset password tidak valid atau sudah kedaluwarsa.')]);
    }
}
