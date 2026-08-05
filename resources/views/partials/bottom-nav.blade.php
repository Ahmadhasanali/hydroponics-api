@auth
    <nav id="bottomNav"
        class="fixed inset-x-0 bottom-0 z-40 grid border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-2px_12px_rgba(0,0,0,0.05)] backdrop-blur-xl lg:hidden {{ $hasFarm ? 'grid-cols-4' : 'grid-cols-3' }}">
        <a href="{{ route('dashboard') }}"
            class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('dashboard') ? 'text-[#d4a020]' : 'text-slate-500' }}">
            <i class="bi {{ request()->routeIs('dashboard') ? 'bi-grid-1x2-fill' : 'bi-grid-1x2' }} text-xl"></i>
            Dashboard
        </a>

        @if ($hasFarm)
            <div class="relative flex flex-col items-center">
                <button id="catatBtn" type="button"
                    class="flex w-full flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('daily-monitoring.create') || request()->routeIs('nutrient-addition.create') || request()->routeIs('ph-down-log.create') ? 'text-[#d4a020]' : 'text-slate-500' }}">
                    <i class="bi bi-plus-circle-fill text-xl"></i>
                    Catat
                </button>
                <div id="catatMenu" class="absolute bottom-full right-0 mb-2 hidden w-52 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl">
                    <a href="{{ route('daily-monitoring.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                        <i class="bi bi-thermometer-half text-base text-slate-400"></i>
                        Monitoring (PPM & pH)
                    </a>
                    <a href="{{ route('nutrient-addition.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                        <i class="bi bi-droplet text-base text-slate-400"></i>
                        AB Mix
                    </a>
                    <a href="{{ route('ph-down-log.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                        <i class="bi bi-flask text-base text-slate-400"></i>
                        pH Down
                    </a>
                </div>
            </div>

            @php
                $riwayatActive = request()->routeIs('daily-monitoring.index') || request()->routeIs('daily-monitoring.edit');
            @endphp
            <a href="{{ route('daily-monitoring.index') }}"
                class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ $riwayatActive ? 'text-[#d4a020]' : 'text-slate-500' }}">
                <i class="bi {{ $riwayatActive ? 'bi-clock-history-fill' : 'bi-clock-history' }} text-xl"></i>
                Riwayat
            </a>
        @else
            <a href="{{ route('farm.create') }}"
                class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold text-slate-500">
                <i class="bi bi-plus-circle text-xl"></i>
                Buat Farm
            </a>
        @endif

        <a href="{{ route('profile') }}"
            class="flex flex-col items-center gap-0.5 py-2.5 text-[10px] font-semibold {{ request()->routeIs('profile') ? 'text-[#d4a020]' : 'text-slate-500' }}">
            <i class="bi {{ request()->routeIs('profile') ? 'bi-person-fill' : 'bi-person' }} text-xl"></i>
            Profil
        </a>
    </nav>
@endauth
