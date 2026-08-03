<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreStaffRequest;
use App\Http\Requests\Farm\UpdateStaffPasswordRequest;
use App\Models\Farm;
use App\Models\Farm\Staff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FarmStaffController extends Controller
{
    public function create(Farm $farm): View
    {
        $this->authorize('manageStaff', $farm);

        return view('farm-members.staff-create', compact('farm'));
    }

    public function store(StoreStaffRequest $request, Farm $farm): RedirectResponse
    {
        $validated = $request->validated();

        $farm->staff()->create($validated);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Akun petugas berhasil dibuat.');
    }

    public function resetPassword(UpdateStaffPasswordRequest $request, Farm $farm, Staff $staff): RedirectResponse
    {
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['password' => $request->validated('password')]);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Password petugas berhasil direset.');
    }

    public function toggle(Farm $farm, Staff $staff): RedirectResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->update(['is_active' => ! $staff->is_active]);

        return redirect()->route('farm.members.index', $farm)
            ->with('success', $staff->is_active ? 'Akun petugas diaktifkan.' : 'Akun petugas dinonaktifkan.');
    }

    public function destroy(Farm $farm, Staff $staff): RedirectResponse
    {
        $this->authorize('manageStaff', $farm);
        abort_unless($staff->farm_id === $farm->id, 404);

        $staff->delete();

        return redirect()->route('farm.members.index', $farm)
            ->with('success', 'Akun petugas dihapus.');
    }
}
