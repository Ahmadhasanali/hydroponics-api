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
        // TODO: implement daily monitoring store with validation
        // $validated = $request->validate([...]);
        // DailyMonitoring::create($validated + ['user_id' => auth()->id()]);

        return redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil disimpan.');
    }

    public function edit(DailyMonitoring $dailyMonitoring): View
    {
        // TODO: implement daily monitoring edit

        return view('daily-monitoring.edit', compact('dailyMonitoring'));
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        // TODO: implement daily monitoring update with validation

        return redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil diperbarui.');
    }

    public function destroy(DailyMonitoring $dailyMonitoring): RedirectResponse
    {
        // TODO: implement daily monitoring delete with authorization

        return redirect()->route('daily-monitoring.index')
            ->with('success', 'Data monitoring berhasil dihapus.');
    }
}
