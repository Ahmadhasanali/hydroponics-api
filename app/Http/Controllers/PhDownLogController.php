<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PhDownLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return $this->errorResponse('farm_id is required.', 422);
        }

        Gate::authorize('view', Farm::findOrFail($farmId));

        $tankIds = Tank::where('farm_id', $farmId)->pluck('id');
        $logs = PhDownLog::whereIn('tank_id', $tankIds)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return $this->paginatedResponse($logs);
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

        $tank = Tank::findOrFail($validated['tank_id']);
        Gate::authorize('update', $tank->farm);

        $log = PhDownLog::create($validated + ['user_id' => $request->user()->id]);

        return $this->successResponse(['ph_down' => $log], 'Data pH Down berhasil disimpan.', 201);
    }

    public function show(PhDownLog $phDownLog): JsonResponse
    {
        Gate::authorize('view', $phDownLog->tank->farm);

        $phDownLog->load(['tank', 'user']);

        return $this->successResponse(['ph_down' => $phDownLog]);
    }

    public function update(Request $request, PhDownLog $phDownLog): JsonResponse
    {
        Gate::authorize('update', $phDownLog->tank->farm);

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        Tank::where('farm_id', $phDownLog->tank->farm_id)
            ->findOrFail($validated['tank_id']);

        $phDownLog->update($validated);

        return $this->successResponse(['ph_down' => $phDownLog], 'Data pH Down berhasil diperbarui.');
    }

    public function destroy(PhDownLog $phDownLog): JsonResponse
    {
        Gate::authorize('update', $phDownLog->tank->farm);

        $phDownLog->delete();

        return $this->successResponse(null, 'Data pH Down berhasil dihapus.');
    }
}
