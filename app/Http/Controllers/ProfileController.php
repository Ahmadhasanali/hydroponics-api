<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $farms = $user->farms()->withCount('tanks')->get();

        return $this->successResponse([
            'user' => $user,
            'farms' => $farms,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return $this->successResponse(['user' => $request->user()->fresh()], 'Profil berhasil diperbarui.');
    }
}
