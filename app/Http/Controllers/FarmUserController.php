<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFarmUserRequest;
use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmUserController extends Controller
{
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $this->authorize('view', $farm);

        $farm->load([
            'users' => function ($query) {
                $query->orderBy('pivot_created_at');
            },
            'staff',
        ]);

        $members = $farm->users->map(function ($user) {
            $user->role = $user->pivot->role;
            unset($user->pivot);

            return $user;
        });

        return $this->successResponse([
            'farm' => [
                'id' => $farm->id,
                'users' => $members,
                'staff' => $farm->staff,
            ],
        ]);
    }

    public function store(StoreFarmUserRequest $request, Farm $farm): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return $this->errorResponse('User dengan email tersebut tidak ditemukan.', 422, [
                'email' => ['User dengan email tersebut tidak ditemukan.'],
            ]);
        }

        if ($farm->users()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User tersebut sudah menjadi anggota farm.', 422, [
                'email' => ['User tersebut sudah menjadi anggota farm.'],
            ]);
        }

        $farm->users()->attach($user->id, ['role' => 'manager']);

        return $this->successResponse(['farm' => $farm->fresh()->load('users')], 'Anggota berhasil ditambahkan.', 201);
    }

    public function destroy(Request $request, Farm $farm, FarmUser $farmUser): JsonResponse
    {
        Gate::authorize('manageMembers', $farm);

        if ($farmUser->farm_id !== $farm->id) {
            return $this->errorResponse('Anggota tidak ditemukan.', 404);
        }

        if ($farmUser->role === 'owner') {
            return $this->errorResponse('Pemilik kebun tidak dapat dihapus.', 422);
        }

        if ($farmUser->user_id === $request->user()->id) {
            return $this->errorResponse('Anda tidak dapat menghapus diri sendiri.', 422);
        }

        $farmUser->delete();

        return $this->successResponse(null, 'Anggota berhasil dihapus.');
    }
}
