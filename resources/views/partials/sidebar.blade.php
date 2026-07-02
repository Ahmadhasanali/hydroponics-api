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
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Hydro Farm</p>
            <p class="text-sm font-semibold text-slate-900">Sistem Manajemen</p>
        </div>
    </div>

    {{-- Separator --}}
    <hr class="my-5 border-slate-200">

    {{-- Farm Info (if selected) --}}
    @isset($selectedFarm)
        <div class="sidebar-text mb-4 rounded-2xl bg-[#ffce54]/10 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d4a020]">Farm Aktif</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedFarm->name }}</p>
            @if($selectedFarm->tanks_count !== null)
                <p class="text-xs text-slate-500">{{ $selectedFarm->tanks_count }} tank</p>
            @endif
        </div>
    @endisset

    {{-- Navigation --}}
    <nav class="flex-1 space-y-1">
        <a href="{{ route('dashboard') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('dashboard') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-grid-1x2-fill text-base"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="{{ route('farm.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('farm.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-buildings-fill text-base"></i>
            <span class="sidebar-text">Data Farm</span>
        </a>

        <a href="{{ route('tank.index') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('tank.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
            <i class="bi bi-water text-base"></i>
            <span class="sidebar-text">Data Tank</span>
        </a>

            @isset($selectedFarm)
                <div class="pt-3">
                    <p class="sidebar-text px-3 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Monitoring</p>
                    <div class="mt-1 space-y-1 border-l-2 border-slate-200 pl-2">
                        <a href="{{ route('daily-monitoring.index') }}"
                            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('daily-monitoring.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
                            <i class="bi bi-thermometer-half text-base"></i>
                            <span class="sidebar-text">Daily Monitoring</span>
                        </a>
                        <a href="{{ route('nutrient-addition.index') }}"
                            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('nutrient-addition.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
                            <i class="bi bi-droplet text-base"></i>
                            <span class="sidebar-text">AB Mix</span>
                        </a>
                        <a href="{{ route('ph-down-log.index') }}"
                            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('ph-down-log.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
                            <i class="bi bi-flask-fill text-base"></i>
                            <span class="sidebar-text">pH Down</span>
                        </a>
                    </div>
                </div>

                <div class="pt-3">
                    <p class="sidebar-text px-3 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Laporan</p>
                    <div class="mt-1 space-y-1 border-l-2 border-slate-200 pl-2">
                        <a href="{{ route('activity-logs.index') }}"
                            class="sidebar-nav-link flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-[#ffce54]/10 hover:text-[#1a1c1e] {{ request()->routeIs('activity-logs.*') ? 'bg-[#ffce54]/15 text-[#1a1c1e]' : '' }}">
                            <i class="bi bi-clock-history text-base"></i>
                            <span class="sidebar-text">Activity Logs</span>
                        </a>
                    </div>
                </div>
            @endisset
    </nav>

    {{-- Bottom --}}
    <hr class="my-4 border-slate-200">

    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit"
            class="sidebar-nav-link flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-rose-50 hover:text-rose-700">
            <i class="bi bi-box-arrow-right text-base"></i>
            <span class="sidebar-text">Keluar</span>
        </button>
    </form>
</aside>
