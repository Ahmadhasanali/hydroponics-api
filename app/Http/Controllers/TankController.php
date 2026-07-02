<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TankController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $farm = Farm::with('tanks')->findOrFail($farmId);

        // TODO: add search/filter by tank name

        return view('tank.index', [
            'farm' => $farm,
            'tanks' => $farm->tanks()->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');

        return view('tank.create', [
            'farmId' => $farmId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        $farmId = $request->session()->get('selected_farm_id');

        // Tank name must be unique within the same farm
        $exists = Tank::where('farm_id', $farmId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Nama tank sudah digunakan di farm ini.'])->withInput();
        }

        Tank::create($validated + [
            'farm_id' => $farmId,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil ditambahkan.');
    }

    public function show(Tank $tank): View
    {
        $tank->load('creator');

        $monitorings = $tank->dailyMonitorings()
            ->with('user')
            ->latest('log_date')
            ->paginate(10, ['*'], 'monitoring_page');

        $nutrientAdditions = $tank->nutrientAdditions()
            ->with('user')
            ->latest('log_date')
            ->paginate(10, ['*'], 'nutrient_page');

        $phDownLogs = $tank->phDownLogs()
            ->with('user')
            ->latest('log_date')
            ->paginate(10, ['*'], 'ph_page');

        return view('tank.show', compact(
            'tank',
            'monitorings',
            'nutrientAdditions',
            'phDownLogs',
        ));
    }

    public function edit(Tank $tank): View
    {
        return view('tank.edit', compact('tank'));
    }

    public function update(Request $request, Tank $tank): RedirectResponse
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

        // Tank name must be unique within the same farm (excluding self)
        $exists = Tank::where('farm_id', $tank->farm_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $tank->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Nama tank sudah digunakan di farm ini.'])->withInput();
        }

        $tank->update($validated);

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil diperbarui.');
    }

    public function destroy(Tank $tank): RedirectResponse
    {
        Gate::authorize('view', $tank->farm);

        $tank->delete();

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil dihapus.');
    }
}
