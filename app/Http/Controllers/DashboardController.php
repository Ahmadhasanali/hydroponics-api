<?php

namespace App\Http\Controllers;

use App\Models\Farm\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $farms = $user->farms()->withCount('tanks')->get();

        if ($farms->isEmpty()) {
            return view('dashboard.index', [
                'farms' => collect(),
                'selectedFarm' => null,
                'tanks' => collect(),
                'activityLogs' => collect(),
                'stats' => [],
            ]);
        }

        $selectedFarmId = $request->session()->get('selected_farm_id');
        $selectedFarm = $farms->firstWhere('id', $selectedFarmId) ?? $farms->first();
        $request->session()->put('selected_farm_id', $selectedFarm->id);

        $tanks = $selectedFarm->tanks()->orderBy('id', 'asc')->get();

        $totalTanks = $tanks->count();
        $activeTanks = $tanks->where('is_active', true)->count();

        $avgPpm = $tanks->avg('current_ppm');
        $avgPh = $tanks->avg('current_ph');
        $avgTemp = $tanks->avg('current_water_temperature');

        $stats = [
            'total_tanks' => $totalTanks,
            'active_tanks' => $activeTanks,
            'avg_ppm' => $avgPpm ? round($avgPpm, 1) : null,
            'avg_ph' => $avgPh ? round($avgPh, 1) : null,
            'avg_temp' => $avgTemp ? round($avgTemp, 1) : null,
        ];

        $activityLogs = ActivityLog::where('farm_id', $selectedFarm->id)
            ->with('user')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'farms',
            'selectedFarm',
            'tanks',
            'activityLogs',
            'stats',
        ));
    }

    public function switchFarm(Request $request): RedirectResponse
    {
        $request->session()->put('selected_farm_id', $request->integer('farm_id'));

        return redirect()->route('dashboard');
    }
}
