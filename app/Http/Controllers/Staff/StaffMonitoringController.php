<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffMonitoringController extends Controller
{
    private function staff(): Staff
    {
        return request()->user();
    }

    private function farmTankIds()
    {
        return Tank::where('farm_id', $this->staff()->farm_id)->pluck('id');
    }

    public function index(): JsonResponse
    {
        $monitorings = DailyMonitoring::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($monitorings);
    }

    public function show(DailyMonitoring $dailyMonitoring): JsonResponse
    {
        abort_unless($dailyMonitoring->staff_id === $this->staff()->id, 403);

        return $this->successResponse(['monitoring' => $dailyMonitoring->load('tank')]);
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

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('Monitoring untuk tank ini pada tanggal tersebut sudah ada.', 422, [
                'log_date' => ['Monitoring untuk tank ini pada tanggal tersebut sudah ada.'],
            ]);
        }

        $monitoring = DailyMonitoring::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return $this->successResponse(['monitoring' => $monitoring], 'Data monitoring berhasil disimpan.', 201);
    }

    public function update(Request $request, DailyMonitoring $dailyMonitoring): JsonResponse
    {
        abort_unless($dailyMonitoring->staff_id === $this->staff()->id, 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ppm' => 'required|numeric|min:0|max:3000',
            'ph' => 'required|numeric|min:0|max:14',
            'water_temperature' => 'nullable|numeric|min:-10|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $exists = DailyMonitoring::where('tank_id', $validated['tank_id'])
            ->where('log_date', $validated['log_date'])
            ->where('id', '!=', $dailyMonitoring->id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Monitoring untuk tank ini pada tanggal tersebut sudah ada.', 422, [
                'log_date' => ['Monitoring untuk tank ini pada tanggal tersebut sudah ada.'],
            ]);
        }

        $dailyMonitoring->update($validated);

        return $this->successResponse(['monitoring' => $dailyMonitoring], 'Data monitoring berhasil diperbarui.');
    }

    public function destroy(DailyMonitoring $dailyMonitoring): JsonResponse
    {
        abort_unless($dailyMonitoring->staff_id === $this->staff()->id, 403);

        $dailyMonitoring->delete();

        return $this->successResponse(null, 'Data monitoring berhasil dihapus.');
    }
}
