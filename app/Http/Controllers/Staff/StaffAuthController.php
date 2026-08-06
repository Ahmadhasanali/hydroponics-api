<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffLoginRequest;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffAuthController extends Controller
{
    public function login(StaffLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $farm = Farm::where('name', $validated['farm_name'])->first();
        $staff = $farm?->staff()->where('username', $validated['username'])->first();

        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            return $this->errorResponse('Nama kebun, username, atau password salah.', 401);
        }

        if (! $staff->is_active) {
            return $this->errorResponse('Akun tidak aktif. Hubungi pemilik kebun.', 403);
        }

        $token = $staff->createToken('staff-token', ['staff'])->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'staff' => $staff,
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout berhasil.');
    }
}
