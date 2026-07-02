<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\Farm\Tank;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TankController extends Controller
{
    public function index(Request $request): View
    {
        $farmId = $request->session()->get('selected_farm_id');
        $farm = Farm::with('tanks')->findOrFail($farmId);

        return view('tank.index', [
            'farm' => $farm,
            'tanks' => $farm->tanks()->orderBy('name')->get(),
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
        // TODO: implement tank store with validation
        // $validated = $request->validate([...]);
        // Tank::create($validated);

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil ditambahkan.');
    }

    public function show(Tank $tank): View
    {
        // TODO: implement tank detail with monitoring history

        return view('tank.show', compact('tank'));
    }

    public function edit(Tank $tank): View
    {
        // TODO: implement tank edit

        return view('tank.edit', compact('tank'));
    }

    public function update(Request $request, Tank $tank): RedirectResponse
    {
        // TODO: implement tank update with validation

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil diperbarui.');
    }

    public function destroy(Tank $tank): RedirectResponse
    {
        // TODO: implement tank delete with authorization

        return redirect()->route('tank.index')
            ->with('success', 'Tank berhasil dihapus.');
    }
}
