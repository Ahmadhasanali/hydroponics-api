<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPhDownController extends Controller
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
        $logs = PhDownLog::where('staff_id', $this->staff()->id)
            ->with('tank')
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($logs);
    }

    public function show(PhDownLog $phDownLog): JsonResponse
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        return $this->successResponse(['ph_down_log' => $phDownLog->load('tank')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $phDownLog = PhDownLog::create($validated + [
            'staff_id' => $this->staff()->id,
            'user_id' => null,
        ]);

        return $this->successResponse(['ph_down_log' => $phDownLog], 'Data pH Down berhasil disimpan.', 201);
    }

    public function update(Request $request, PhDownLog $phDownLog): JsonResponse
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_unless($this->farmTankIds()->contains($validated['tank_id']), 403);

        $phDownLog->update($validated);

        return $this->successResponse(['ph_down_log' => $phDownLog], 'Data pH Down berhasil diperbarui.');
    }

    public function destroy(PhDownLog $phDownLog): JsonResponse
    {
        abort_unless($phDownLog->staff_id === $this->staff()->id, 403);

        $phDownLog->delete();

        return $this->successResponse(null, 'Data pH Down berhasil dihapus.');
    }
}
