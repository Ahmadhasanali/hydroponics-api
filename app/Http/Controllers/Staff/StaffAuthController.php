<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffLoginRequest;
use App\Models\Farm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('staff')->check()) {
            return redirect()->route('staff.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return view('staff.auth.login');
    }

    public function login(StaffLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $farm = Farm::where('name', $validated['farm_name'])->first();
        $staff = $farm?->staff()->where('username', $validated['username'])->first();

        if (! $staff || ! Hash::check($validated['password'], $staff->password)) {
            return back()
                ->withInput($request->only('farm_name', 'username'))
                ->withErrors(['farm_name' => 'Nama kebun, username, atau password salah.']);
        }

        if (! $staff->is_active) {
            return back()
                ->withInput($request->only('farm_name', 'username'))
                ->withErrors(['farm_name' => 'Akun tidak aktif. Hubungi pemilik kebun.']);
        }

        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();

        return redirect()->route('staff.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
