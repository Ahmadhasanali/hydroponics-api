{{-- Mobile overlay --}}
<div id="mobileSidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/40 opacity-0 pointer-events-none transition duration-300 lg:hidden"></div>

{{-- Sidebar --}}
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-30 w-72 -translate-x-full transform overflow-y-auto bg-white/95 px-4 py-6 shadow-2xl backdrop-blur-xl transition-all duration-300 ease-in-out
           lg:static lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:flex lg:flex-col lg:w-[280px] lg:shadow-none lg:overflow-hidden">

    {{-- Mobile header (close button) --}}
    <div class="flex items-center justify-between lg:hidden">
        <div class="space-y-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 text-lg font-semibold text-slate-900">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm shadow-emerald-500/20">
                    <i class="bi bi-droplet-half"></i>
                </span>
                Hydro Monitor
            </a>
            <p class="text-sm leading-6 text-slate-500">Pantau kondisi nutrisi dan tank terkini.</p>
        </div>
        <button id="closeSidebarBtn" class="inline-flex h-10 w-10 items-center justify-center rounded-3xl bg-slate-100 text-slate-700 transition hover:bg-slate-200">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Desktop logo area --}}
    <div class="hidden lg:flex lg:items-center lg:gap-3 lg:shrink-0">
        <a href="{{ route('dashboard') }}"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm shadow-emerald-500/20">
            <i class="bi bi-droplet-half text-lg"></i>
        </a>
        <div id="sidebarLogoText" class="sidebar-text space-y-0.5 overflow-hidden">
            <p class="whitespace-nowrap text-base font-semibold text-slate-900">Hydro Monitor</p>
            <p class="whitespace-nowrap text-xs leading-5 text-slate-500">Pantau kondisi &amp; nutrisi</p>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="mt-6 flex-1 space-y-1 text-sm font-medium text-slate-700">
        <a href="{{ route('dashboard') }}"
            class="sidebar-nav-link flex items-center gap-3 rounded-3xl px-3 py-3 text-slate-900 shadow-sm shadow-slate-900/5 transition hover:bg-slate-50 {{ request()->routeIs('dashboard') ? 'bg-slate-100' : '' }}"
            title="Dashboard">
            <i class="bi bi-speedometer2 text-lg shrink-0"></i>
            <span class="sidebar-text whitespace-nowrap overflow-hidden">Dashboard</span>
        </a>
        <a href="#monitoring"
            class="sidebar-nav-link flex items-center gap-3 rounded-3xl px-3 py-3 transition hover:bg-slate-50"
            title="Monitoring">
            <i class="bi bi-bar-chart-line text-lg shrink-0"></i>
            <span class="sidebar-text whitespace-nowrap overflow-hidden">Monitoring</span>
        </a>
        <a href="#tanks"
            class="sidebar-nav-link flex items-center gap-3 rounded-3xl px-3 py-3 transition hover:bg-slate-50"
            title="Tanks">
            <i class="bi bi-droplet text-lg shrink-0"></i>
            <span class="sidebar-text whitespace-nowrap overflow-hidden">Tanks</span>
        </a>
        <a href="#reports"
            class="sidebar-nav-link flex items-center gap-3 rounded-3xl px-3 py-3 transition hover:bg-slate-50"
            title="Laporan">
            <i class="bi bi-file-earmark-text text-lg shrink-0"></i>
            <span class="sidebar-text whitespace-nowrap overflow-hidden">Laporan</span>
        </a>
        <a href="#settings"
            class="sidebar-nav-link flex items-center gap-3 rounded-3xl px-3 py-3 transition hover:bg-slate-50"
            title="Pengaturan">
            <i class="bi bi-gear text-lg shrink-0"></i>
            <span class="sidebar-text whitespace-nowrap overflow-hidden">Pengaturan</span>
        </a>
    </nav>

    {{-- Status widget (hidden when collapsed) --}}
    <div id="sidebarStatusWidget" class="sidebar-text mt-8 overflow-hidden rounded-[2rem] border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700">
        <p class="whitespace-nowrap font-semibold text-slate-900">Status Sistem</p>
        <p class="mt-2 text-xs leading-5 text-slate-500">Semua sensor terhubung, data rutin tersedia setiap 15 menit.</p>
    </div>
</aside>
