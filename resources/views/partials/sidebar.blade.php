{{-- Mobile overlay --}}
<div id="mobileSidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/40 opacity-0 pointer-events-none transition duration-300 lg:hidden"></div>

{{-- Sidebar --}}
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-30 w-72 -translate-x-full transform overflow-y-auto bg-white px-4 py-6 shadow-2xl backdrop-blur-xl transition-all duration-300 ease-in-out
           lg:static lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:flex lg:flex-col lg:w-[280px] lg:shadow-none lg:overflow-hidden">

    {{-- Mobile header --}}
    <div class="flex items-center justify-between lg:hidden">
        <div class="space-y-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 text-lg font-semibold text-slate-900">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20">
                    <i class="bi bi-droplet-half"></i>
                </span>
                Hydro Farm
            </a>
            <p class="text-sm leading-6 text-slate-500">Sistem Manajemen Hidroponik</p>
        </div>
        <button id="closeSidebarBtn" class="inline-flex h-10 w-10 items-center justify-center rounded-3xl bg-slate-100 text-slate-700 transition hover:bg-slate-200">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

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

    </nav>

    {{-- User / Logout --}}
    <hr class="my-4 border-slate-100">

    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <button type="submit"
            class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-red-50 hover:text-red-600">
            <i class="bi bi-box-arrow-right text-base"></i>
            Logout
        </button>
    </form>

</aside>
