<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function monitoring(Request $request): JsonResponse
    {
        $this->authorizeReportScope($request);

        $farmId = $request->integer('farm_id');
        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($farmId && $tankId && $startDate && $endDate) {
            $query = DailyMonitoring::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

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

        return $this->successResponse(['aggregates' => $aggregates]);
    }

    public function nutrient(Request $request): JsonResponse
    {
        $this->authorizeReportScope($request);

        $farmId = $request->integer('farm_id');
        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($farmId && $tankId && $startDate && $endDate) {
            $query = NutrientAddition::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

            $aggregates = [
                'count' => $query->count(),
                'total_nutrient_a_ml' => $query->sum('nutrient_a_ml'),
                'total_nutrient_b_ml' => $query->sum('nutrient_b_ml'),
            ];
        }

        return $this->successResponse(['aggregates' => $aggregates]);
    }

    public function phDown(Request $request): JsonResponse
    {
        $this->authorizeReportScope($request);

        $farmId = $request->integer('farm_id');
        $tankId = $request->input('tank_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $aggregates = null;
        if ($farmId && $tankId && $startDate && $endDate) {
            $query = PhDownLog::where('tank_id', $tankId)
                ->whereBetween('log_date', [$startDate, $endDate]);

            $aggregates = [
                'count' => $query->count(),
                'total_ph_down_ml' => $query->sum('ph_down_ml'),
            ];
        }

        return $this->successResponse(['aggregates' => $aggregates]);
    }

    private function authorizeReportScope(Request $request): void
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return;
        }

        Gate::authorize('view', Farm::findOrFail($farmId));

        $tankId = $request->input('tank_id');

        if ($tankId) {
            abort_unless(
                Tank::where('farm_id', $farmId)->whereKey($tankId)->exists(),
                403,
            );
        }
    }
}
