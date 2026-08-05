<?php

namespace App\Http\Controllers;

use App\Models\Farm\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $farms = $user->farms()->withCount('tanks')->get();

        if ($farms->isEmpty()) {
            return $this->successResponse([
                'farms' => [],
                'selectedFarm' => null,
                'tanks' => [],
                'activityLogs' => [],
                'stats' => [],
            ]);
        }

        $farmId = $request->integer('farm_id');
        $selectedFarm = $farmId
            ? $farms->firstWhere('id', $farmId)
            : $farms->first();

        if (! $selectedFarm) {
            $selectedFarm = $farms->first();
        }

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

        return $this->successResponse([
            'farms' => $farms,
            'selectedFarm' => $selectedFarm,
            'tanks' => $tanks,
            'activityLogs' => $activityLogs,
            'stats' => $stats,
        ]);
    }
}