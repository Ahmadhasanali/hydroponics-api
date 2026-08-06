<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReportController extends Controller
{
    private function farmTanks(Request $request)
    {
        return Tank::where('farm_id', $request->user()->farm_id)->orderBy('name')->get();
    }

    public function monitoring(Request $request): JsonResponse
    {
        $tanks = $this->farmTanks($request);
        $tankIds = $tanks->pluck('id');

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date']) && $tankIds->contains($request->input('tank_id'))) {
            $query = DailyMonitoring::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = [
                'count' => $query->count(),
                'avg_ppm' => $query->avg('ppm'),
                'highest_ppm' => $query->max('ppm'),
                'lowest_ppm' => $query->min('ppm'),
                'avg_ph' => $query->avg('ph'),
                'highest_ph' => $query->max('ph'),
                'lowest_ph' => $query->min('ph'),
            ];
        }

        return $this->successResponse([
            'tanks' => $tanks,
            'aggregates' => $aggregates,
        ]);
    }

    public function nutrient(Request $request): JsonResponse
    {
        $tanks = $this->farmTanks($request);
        $tankIds = $tanks->pluck('id');

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date']) && $tankIds->contains($request->input('tank_id'))) {
            $query = NutrientAddition::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = [
                'count' => $query->count(),
                'total_nutrient_a_ml' => $query->sum('nutrient_a_ml'),
                'total_nutrient_b_ml' => $query->sum('nutrient_b_ml'),
            ];
        }

        return $this->successResponse([
            'tanks' => $tanks,
            'aggregates' => $aggregates,
        ]);
    }

    public function phDown(Request $request): JsonResponse
    {
        $tanks = $this->farmTanks($request);
        $tankIds = $tanks->pluck('id');

        $aggregates = null;
        if ($request->filled(['tank_id', 'start_date', 'end_date']) && $tankIds->contains($request->input('tank_id'))) {
            $query = PhDownLog::where('tank_id', $request->input('tank_id'))
                ->whereBetween('log_date', [$request->input('start_date'), $request->input('end_date')]);

            $aggregates = [
                'count' => $query->count(),
                'total_ph_down_ml' => $query->sum('ph_down_ml'),
            ];
        }

        return $this->successResponse([
            'tanks' => $tanks,
            'aggregates' => $aggregates,
        ]);
    }
}
