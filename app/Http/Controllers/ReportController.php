<?php

namespace App\Http\Controllers;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function monitoring(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->orderBy('name')->get();

        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($tankId && $startDate && $endDate) {
            $query = DailyMonitoring::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

            $aggregates = (object) [
                'count' => $query->count(),
                'avg_ppm' => $query->avg('ppm'),
                'highest_ppm' => $query->max('ppm'),
                'lowest_ppm' => $query->min('ppm'),
                'avg_ph' => $query->avg('ph'),
                'highest_ph' => $query->max('ph'),
                'lowest_ph' => $query->min('ph'),
            ];
        }

        return view('reports.monitoring', compact('tanks', 'tankId', 'startDate', 'endDate', 'aggregates'));
    }

    public function nutrient(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->orderBy('name')->get();

        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($tankId && $startDate && $endDate) {
            $query = NutrientAddition::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

            $aggregates = (object) [
                'count' => $query->count(),
                'total_nutrient_a_ml' => $query->sum('nutrient_a_ml'),
                'total_nutrient_b_ml' => $query->sum('nutrient_b_ml'),
            ];
        }

        return view('reports.nutrient', compact('tanks', 'tankId', 'startDate', 'endDate', 'aggregates'));
    }

    public function phDown(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->orderBy('name')->get();

        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($tankId && $startDate && $endDate) {
            $query = PhDownLog::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

            $aggregates = (object) [
                'count' => $query->count(),
                'total_ph_down_ml' => $query->sum('ph_down_ml'),
            ];
        }

        return view('reports.ph-down', compact('tanks', 'tankId', 'startDate', 'endDate', 'aggregates'));
    }
}
