{{-- Sidebar --}}
<aside id="sidebar"
    class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-[280px] lg:shrink-0 lg:flex-col overflow-y-auto border-r border-slate-200 bg-white px-4 py-6">

    {{-- Desktop brand area --}}
    <div class="hidden lg:flex lg:items-center lg:gap-3 lg:shrink-0">
        <a href="{{ route('dashboard') }}"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20">
            <i class="bi bi-droplet-half text-lg"></i>
        </a>
        <div class="sidebar-text">
            <p class="text-sm font-semibold text-slate-900">Hydro Farm</p>
            <p class="text-xs text-slate-500">Sistem Manajemen Hidroponik</p>
        </div>
    </div>

    {{-- Divider --}}
    <hr class="my-6 border-slate-100">

    {{-- Navigation --}}
    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('dashboard') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-grid-1x2-fill text-base"></i>
            Dashboard
        </a>

        {{-- Farm --}}
        <a href="{{ route('farm.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('farm.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-buildings-fill text-base"></i>
            Farm
        </a>

        @if ($hasFarm)
            {{-- Tank --}}
            <a href="{{ route('tank.index') }}"
                class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('tank.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
                <i class="bi bi-water text-base"></i>
                Tank
            </a>

        {{-- Monitoring section --}}
        <p class="sidebar-text mt-4 px-3 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Monitoring</p>

        <a href="{{ route('daily-monitoring.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('daily-monitoring.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-thermometer-half text-base"></i>
            Daily Monitoring
        </a>

        <a href="{{ route('nutrient-addition.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('nutrient-addition.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-droplet text-base"></i>
            AB Mix
        </a>

        <a href="{{ route('ph-down-log.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('ph-down-log.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-flask text-base"></i>
            pH Down
        </a>

        <a href="{{ route('farm.reminders.calendar', session('selected_farm_id', auth()->user()->farms()->first()->id)) }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('farm.reminders.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-bell text-base"></i>
            Reminder
        </a>

        {{-- Reports section --}}
        <p class="sidebar-text mt-4 px-3 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Laporan</p>

        <a href="{{ route('reports.monitoring') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('reports.monitoring') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-bar-chart-line text-base"></i>
            Monitoring
        </a>

        <a href="{{ route('reports.nutrient') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('reports.nutrient') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-pie-chart text-base"></i>
            AB Mix
        </a>

        <a href="{{ route('reports.ph-down') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('reports.ph-down') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-graph-down text-base"></i>
            pH Down
        </a>

        {{-- Activity Logs --}}
        <a href="{{ route('activity-logs.index') }}"
            class="sidebar-nav-link mt-4 flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('activity-logs.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-clock-history text-base"></i>
            Activity Logs
        </a>
        @else
            <a href="{{ route('farm.create') }}"
                class="flex items-center gap-3 rounded-2xl bg-[#ffce54] px-3 py-2.5 text-sm font-semibold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                <i class="bi bi-plus-lg text-base"></i>
                Buat Farm Baru
            </a>
        @endif

    </nav>

    {{-- User / Logout --}}
    <hr class="my-4 border-slate-100">

    <form method="POST" action="{{ route('logout') }}" class="w-full js-logout-form">
        @csrf
        <button type="submit"
            class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-red-50 hover:text-red-600">
            <i class="bi bi-box-arrow-right text-base"></i>
            Logout
        </button>
    </form>

</aside>
