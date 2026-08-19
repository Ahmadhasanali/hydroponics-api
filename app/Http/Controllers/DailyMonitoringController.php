<?php

namespace App\Http\Controllers;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyMonitoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return $this->errorResponse('farm_id is required.', 422);
        }

        $tankIds = Tank::where('farm_id', $farmId)->pluck('id');

        $monitorings = DailyMonitoring::whereIn('tank_id', $tankIds)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($monitorings);
    }

    public function store(Request $request): JsonResponse
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
            return $this->errorResponse('Monitoring untuk tank ini pada tanggal tersebut sudah ada.', 422, [
                'log_date' => ['Monitoring untuk tank ini pada tanggal tersebut sudah ada.'],
            ]);
        }

        $tank = Tank::find($validated['tank_id']);
        $warnings = $this->checkTargetRange($validated, $tank);

        $monitoring = DailyMonitoring::create($validated + ['user_id' => $request->user()->id]);

        return $this->successResponse([
            'monitoring' => $monitoring,
            'warnings' => $warnings,
        ], 'Data monitoring berhasil disimpan.', 201);
    }

    public function show(DailyMonitoring $dailyMonitoring): JsonResponse
    {
        $dailyMonitoring->load(['tank', 'user']);

        return $this->successResponse(['monitoring' => $dailyMonitoring]);
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): JsonResponse
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
            return $this->errorResponse('Monitoring untuk tank ini pada tanggal tersebut sudah ada.', 422, [
                'log_date' => ['Monitoring untuk tank ini pada tanggal tersebut sudah ada.'],
            ]);
        }

        $tank = Tank::find($validated['tank_id']);
        $warnings = $this->checkTargetRange($validated, $tank);

        $dailyMonitoring->update($validated);

        return $this->successResponse([
            'monitoring' => $dailyMonitoring,
            'warnings' => $warnings,
        ], 'Data monitoring berhasil diperbarui.');
    }

    public function destroy(DailyMonitoring $dailyMonitoring): JsonResponse
    {
        $dailyMonitoring->delete();

        return $this->successResponse(null, 'Data monitoring berhasil dihapus.');
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
