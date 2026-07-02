<?php

namespace App\Http\Controllers;

use App\Models\Farm\ActivityLog;
use App\Models\Farm\DailyMonitoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'latestMonitorings' => collect(),
                'activityLogs' => collect(),
                'stats' => [],
            ]);
        }

        $selectedFarmId = $request->session()->get('selected_farm_id');
        $selectedFarm = $farms->firstWhere('id', $selectedFarmId) ?? $farms->first();
        $request->session()->put('selected_farm_id', $selectedFarm->id);

        $tanks = $selectedFarm->tanks()->orderBy('name')->get();

        $tankIds = $tanks->pluck('id');

        $latestMonitorings = DailyMonitoring::whereIn('tank_id', $tankIds)
            ->whereIn('id', function ($q) use ($tankIds) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('daily_monitorings')
                    ->whereIn('tank_id', $tankIds)
                    ->groupBy('tank_id');
            })
            ->get()
            ->keyBy('tank_id');

        $totalTanks = $tanks->count();
        $activeTanks = $tanks->where('is_active', true)->count();

        $avgPpm = $latestMonitorings->avg('ppm');
        $avgPh = $latestMonitorings->avg('ph');
        $avgTemp = $latestMonitorings->avg('water_temperature');

        $stats = [
            'total_tanks' => $totalTanks,
            'active_tanks' => $activeTanks,
            'avg_ppm' => $avgPpm ? round($avgPpm, 1) : null,
            'avg_ph' => $avgPh ? round($avgPh, 1) : null,
            'avg_temp' => $avgTemp ? round($avgTemp, 1) : null,
        ];

        $activityLogs = ActivityLog::where('farm_id', $selectedFarm->id)
            ->with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'farms',
            'selectedFarm',
            'tanks',
            'latestMonitorings',
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
