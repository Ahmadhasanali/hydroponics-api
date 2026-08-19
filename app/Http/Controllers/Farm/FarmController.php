<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFarmRequest;
use App\Http\Requests\Farm\UpdateFarmRequest;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FarmController extends Controller
{
    public function index(): JsonResponse
    {
        $farms = auth()->user()->farms()->withCount('tanks')->get();

        return $this->successResponse(['farms' => $farms]);
    }

    public function store(StoreFarmRequest $request): JsonResponse
    {
        $farm = Farm::query()->create(
            $request->validated() + ['created_by' => $request->user()->id]
        );
        $farm->users()->attach($request->user()->id, ['role' => 'owner']);

        return $this->successResponse(['farm' => $farm], 'Farm berhasil ditambahkan.', 201);
    }

    public function show(Farm $farm): JsonResponse
    {
        $farm->load(['tanks', 'users']);

        $farm->users->each(function ($user) {
            $user->role = $user->pivot->role;
            unset($user->pivot);
        });

        return $this->successResponse(['farm' => $farm]);
    }

    public function update(UpdateFarmRequest $request, Farm $farm): JsonResponse
    {
        Gate::authorize('update', $farm);

        $farm->update($request->validated());

        return $this->successResponse(['farm' => $farm], 'Farm berhasil diperbarui.');
    }

    public function destroy(Farm $farm): JsonResponse
    {
        Gate::authorize('delete', $farm);

        $farm->tanks()->each(fn ($tank) => $tank->delete());
        $farm->activityLogs()->delete();
        $farm->delete();

        return $this->successResponse(null, 'Farm berhasil dihapus.');
    }

    public function transferOwnership(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('transferOwnership', $farm);

        $validated = $request->validate([
            'new_owner_id' => ['required', 'exists:users,id'],
        ]);

        $newOwner = $farm->users()->findOrFail($validated['new_owner_id']);

        if ($newOwner->id === $request->user()->id) {
            return $this->errorResponse('Anda tidak dapat mentransfer kepemilikan ke diri sendiri.', 422, [
                'new_owner_id' => ['Anda tidak dapat mentransfer kepemilikan ke diri sendiri.'],
            ]);
        }

        DB::transaction(function () use ($farm, $newOwner, $request) {
            $farm->users()->updateExistingPivot($newOwner->id, ['role' => 'owner']);
            $farm->users()->updateExistingPivot($request->user()->id, ['role' => 'manager']);
        });

        return $this->successResponse(['farm' => $farm], 'Kepemilikan kebun berhasil ditransfer.');
    }
}
