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
        $stats = [
            'total_tanks' => $tanks->count(),
            'active_tanks' => $tanks->where('is_active', true)->count(),
            'avg_ppm' => $tanks->avg('current_ppm') ? round($tanks->avg('current_ppm'), 1) : null,
            'avg_ph' => $tanks->avg('current_ph') ? round($tanks->avg('current_ph'), 1) : null,
            'avg_temp' => $tanks->avg('current_water_temperature') ? round($tanks->avg('current_water_temperature'), 1) : null,
        ];

        return view('staff.dashboard.index', compact('farm', 'stats'));
    }
}
