<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreStaffRequest;
use App\Http\Requests\Farm\UpdateStaffPasswordRequest;
use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Http\JsonResponse;

class FarmStaffController extends Controller
{
    public function store(StoreStaffRequest $request, Farm $farm): JsonResponse
    {
        $this->authorize('manageStaff', $farm);

        $staff = $farm->staff()->create($request->validated());

        return $this->successResponse(['staff' => $staff], 'Akun petugas berhasil dibuat.', 201);
    }

    public function resetPassword(UpdateStaffPasswordRequest $request, Farm $farm, Staff $staff): JsonResponse
    {
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['password' => $request->validated('password')]);

        return $this->successResponse(null, 'Password petugas berhasil direset.');
    }

    public function toggle(Farm $farm, Staff $staff): JsonResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['is_active' => ! $staff->is_active]);

        return $this->successResponse(
            ['staff' => $staff],
            $staff->is_active ? 'Akun petugas diaktifkan.' : 'Akun petugas dinonaktifkan.'
        );
    }

    public function destroy(Farm $farm, Staff $staff): JsonResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->delete();

        return $this->successResponse(null, 'Akun petugas dihapus.');
    }
}
