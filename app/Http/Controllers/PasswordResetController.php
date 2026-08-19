<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! $user->is_admin) {
            Password::broker()->sendResetLink($request->only('email'));
        }

        return $this->successResponse(null, __('Jika email terdaftar, kami telah mengirim link reset password ke email Anda.'));
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user !== null && $user->is_admin) {
            return $this->errorResponse(__('Link reset password tidak valid atau sudah kedaluwarsa.'), 422);
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

        if ($status !== Password::PASSWORD_RESET) {
            return $this->errorResponse(__('Link reset password tidak valid atau sudah kedaluwarsa.'), 422);
        }

        return $this->successResponse(null, __('Password berhasil direset.'));
    }
}
