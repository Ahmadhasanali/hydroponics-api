<?php

namespace App\Http\Controllers;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->pluck('id');

        // TODO: add search/filter by tank name, date range

        // TODO: add search/filter by tank and date range

        $monitorings = DailyMonitoring::whereIn('tank_id', $tanks)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return view('daily-monitoring.index', compact('monitorings'));
    }

    public function create(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('daily-monitoring.create', compact('tanks'));
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

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        $tank = Tank::find($validated['tank_id']);
        $warnings = $this->checkTargetRange($validated, $tank);

        DailyMonitoring::create($validated + ['user_id' => $request->user()->id]);

        $redirect = redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil disimpan.');

        if ($warnings) {
            $redirect->with('warning', $warnings);
        }

        return $redirect;
    }

    public function edit(DailyMonitoring $dailyMonitoring): View
    {
        $farmId = request()->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('daily-monitoring.edit', compact('dailyMonitoring', 'tanks'));
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->where('id', '!=', $dailyMonitoring->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['log_date' => 'Monitoring untuk tank ini pada tanggal tersebut sudah ada.'])->withInput();
        }

        $tank = Tank::find($validated['tank_id']);
        $warnings = $this->checkTargetRange($validated, $tank);

        $dailyMonitoring->update($validated);

        $redirect = redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil diperbarui.');

        if ($warnings) {
            $redirect->with('warning', $warnings);
        }

        return $redirect;
    }

    public function destroy(DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        $dailyMonitoring->delete();

        return redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil dihapus.');
    }

    private function checkTargetRange(array $validated, Tank $tank): ?string
    {
        $issues = [];

        if ($tank->target_ppm_min !== null && $validated['ppm'] < $tank->target_ppm_min) {
            $issues[] = 'PPM ('.number_format($validated['ppm'], 1).') di bawah target minimum ('.number_format($tank->target_ppm_min, 1).').';
        }
        if ($tank->target_ppm_max !== null && $validated['ppm'] > $tank->target_ppm_max) {
            $issues[] = 'PPM ('.number_format($validated['ppm'], 1).') di atas target maksimum ('.number_format($tank->target_ppm_max, 1).').';
        }
        if ($tank->target_ph_min !== null && $validated['ph'] < $tank->target_ph_min) {
            $issues[] = 'pH ('.number_format($validated['ph'], 1).') di bawah target minimum ('.number_format($tank->target_ph_min, 1).').';
        }
        if ($tank->target_ph_max !== null && $validated['ph'] > $tank->target_ph_max) {
            $issues[] = 'pH ('.number_format($validated['ph'], 1).') di atas target maksimum ('.number_format($tank->target_ph_max, 1).').';
        }

        return $issues ? implode(' ', $issues) : null;
    }
}
