<?php

namespace App\Http\Controllers;

use App\Models\Farm\Tank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $farmId = $request->integer('farm_id');

        if (! $farmId) {
            return $this->errorResponse('farm_id is required.', 422);
        }

        $tanks = Tank::where('farm_id', $farmId)
            ->orderBy('id')
            ->get();

        return $this->successResponse(['tanks' => $tanks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => 'required|exists:farms,id',
            'name' => 'required|string|max:255',
            'capacity_liter' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'target_ppm_min' => 'nullable|numeric|min:0|max:3000',
            'target_ppm_max' => 'nullable|numeric|min:0|max:3000',
            'target_ph_min' => 'nullable|numeric|min:0|max:14',
            'target_ph_max' => 'nullable|numeric|min:0|max:14',
            'is_active' => 'boolean',
        ]);

        $exists = Tank::where('farm_id', $validated['farm_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('Nama tank sudah digunakan di farm ini.', 422, [
                'name' => ['Nama tank sudah digunakan di farm ini.'],
            ]);
        }

        $tank = Tank::create($validated + [
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse(['tank' => $tank], 'Tank berhasil ditambahkan.', 201);
    }

    public function show(Tank $tank): JsonResponse
    {
        $tank->load('creator');

        $monitorings = $tank->dailyMonitorings()
            ->with('user')
            ->latest('log_date')
            ->paginate(10);

        $nutrientAdditions = $tank->nutrientAdditions()
            ->with('user')
            ->latest('log_date')
            ->paginate(10);

        $phDownLogs = $tank->phDownLogs()
            ->with('user')
            ->latest('log_date')
            ->paginate(10);

        return $this->successResponse([
            'tank' => $tank,
            'monitorings' => $monitorings,
            'nutrientAdditions' => $nutrientAdditions,
            'phDownLogs' => $phDownLogs,
        ]);
    }

    public function update(Request $request, Tank $tank): JsonResponse
    {
        Gate::authorize('view', $tank->farm);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'capacity_liter' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'target_ppm_min' => 'nullable|numeric|min:0|max:3000',
            'target_ppm_max' => 'nullable|numeric|min:0|max:3000',
            'target_ph_min' => 'nullable|numeric|min:0|max:14',
            'target_ph_max' => 'nullable|numeric|min:0|max:14',
            'is_active' => 'boolean',
        ]);

        $exists = Tank::where('farm_id', $tank->farm_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $tank->id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Nama tank sudah digunakan di farm ini.', 422, [
                'name' => ['Nama tank sudah digunakan di farm ini.'],
            ]);
        }

        $tank->update($validated);

        return $this->successResponse(['tank' => $tank], 'Tank berhasil diperbarui.');
    }

    public function destroy(Tank $tank): JsonResponse
    {
        Gate::authorize('view', $tank->farm);

        $tank->delete();

        return $this->successResponse(null, 'Tank berhasil dihapus.');
    }
}
