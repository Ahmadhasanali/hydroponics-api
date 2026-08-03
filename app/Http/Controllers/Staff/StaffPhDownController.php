<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffPhDownController extends Controller
{
    private function staff(): Staff
    {
        return auth('staff')->user();
    }

    private function farmTanks()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->orderBy('name')->get();
    }

    public function index(): View
    {
        $logs = PhDownLog::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return view('staff.ph-down.index', compact('logs'));
    }

    public function create(): View
    {
        return view('staff.ph-down.create', ['tanks' => $this->farmTanks()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        PhDownLog::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return redirect()->route('staff.ph-down.index')
            ->with('success', 'Data pH Down berhasil disimpan.');
    }

    public function edit(PhDownLog $phDownLog): View
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        return view('staff.ph-down.edit', [
            'phDownLog' => $phDownLog,
            'tanks' => $this->farmTanks(),
        ]);
    }

    public function update(Request $request, PhDownLog $phDownLog): RedirectResponse
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $phDownLog->update($validated);

        return redirect()->route('staff.ph-down.index')
            ->with('success', 'Data pH Down berhasil diperbarui.');
    }

    public function destroy(PhDownLog $phDownLog): RedirectResponse
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        $phDownLog->delete();

        return redirect()->route('staff.ph-down.index')
            ->with('success', 'Data pH Down berhasil dihapus.');
    }
}
