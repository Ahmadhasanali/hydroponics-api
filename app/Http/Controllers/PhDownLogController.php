<?php

namespace App\Http\Controllers;

use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhDownLogController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)->pluck('id');
        $logs = PhDownLog::whereIn('tank_id', $tanks)
            ->with(['tank', 'user'])
            ->latest('log_date')
            ->paginate(20);

        return view('ph-down-log.index', compact('logs'));
    }

    public function create(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('ph-down-log.create', compact('tanks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        PhDownLog::create($validated + ['user_id' => auth()->id()]);

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil disimpan.');
    }

    public function edit(Request $request, PhDownLog $phDownLog): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $tanks = Tank::where('farm_id', $farmId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('ph-down-log.edit', compact('phDownLog', 'tanks'));
    }

    public function update(Request $request, PhDownLog $phDownLog): RedirectResponse
    {
        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'log_date' => 'required|date',
            'ph_before' => 'required|numeric|min:0|max:14',
            'ph_after' => 'required|numeric|min:0|max:14|lt:ph_before',
            'ph_down_ml' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $phDownLog->update($validated);

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil diperbarui.');
    }

    public function destroy(PhDownLog $phDownLog): RedirectResponse
    {
        $phDownLog->delete();

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil dihapus.');
    }
}
