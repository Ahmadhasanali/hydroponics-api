<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffMonitoringController extends Controller
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
        $monitorings = DailyMonitoring::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return view('staff.monitoring.index', compact('monitorings'));
    }

    public function create(): View
    {
        return view('staff.monitoring.create', ['tanks' => $this->farmTanks()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        DailyMonitoring::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil disimpan.');
    }

    public function edit(DailyMonitoring $dailyMonitoring): View
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        return view('staff.monitoring.edit', [
            'dailyMonitoring' => $dailyMonitoring,
            'tanks' => $this->farmTanks(),
        ]);
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tank = Tank::where('id', $validated['tank_id'])
            ->where('farm_id', $this->staff()->farm_id)
            ->first();

        if (! $tank) {
            abort(403);
        }

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->where('id', '!=', $dailyMonitoring->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        $dailyMonitoring->update($validated);

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil diperbarui.');
    }

    public function destroy(DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        abort_unless($this->owns($dailyMonitoring), 403);

        $dailyMonitoring->delete();

        return redirect()->route('staff.monitoring.index')
            ->with('success', 'Data monitoring berhasil dihapus.');
    }

    private function owns(DailyMonitoring $dailyMonitoring): bool
    {
        return $dailyMonitoring->staff_id === $this->staff()->id;
    }
}
