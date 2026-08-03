<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function index(): View
    {
        $farm = auth('staff')->user()->farm->load('tanks');

        $tanks = $farm->tanks;
        $avgPpm = $tanks->avg('current_ppm');
        $avgPh = $tanks->avg('current_ph');
        $avgTemp = $tanks->avg('current_water_temperature');
        $stats = [
            'total_tanks' => $tanks->count(),
            'active_tanks' => $tanks->where('is_active', true)->count(),
            'avg_ppm' => $avgPpm !== null ? round($avgPpm, 1) : null,
            'avg_ph' => $avgPh !== null ? round($avgPh, 1) : null,
            'avg_temp' => $avgTemp !== null ? round($avgTemp, 1) : null,
        ];

        return view('staff.dashboard.index', compact('farm', 'stats'));
    }
}
