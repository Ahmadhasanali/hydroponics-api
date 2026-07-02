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
        // TODO: implement pH down store with validation
        // $validated = $request->validate([...]);
        // PhDownLog::create($validated + ['user_id' => auth()->id()]);

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil disimpan.');
    }

    public function edit(PhDownLog $phDownLog): View
    {
        // TODO: implement pH down edit

        return view('ph-down-log.edit', compact('phDownLog'));
    }

    public function update(Request $request, PhDownLog $phDownLog): RedirectResponse
    {
        // TODO: implement pH down update with validation

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil diperbarui.');
    }

    public function destroy(PhDownLog $phDownLog): RedirectResponse
    {
        // TODO: implement pH down delete with authorization

        return redirect()->route('ph-down-log.index')
            ->with('success', 'Data pH Down berhasil dihapus.');
    }
}
