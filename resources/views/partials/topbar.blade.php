<header class="border-b border-slate-200/80 bg-white/90 px-4 py-4 shadow-sm shadow-slate-900/5 backdrop-blur-xl lg:px-6">
    <div class="flex items-center justify-between gap-4 lg:hidden">
        <button id="openSidebarBtn" class="inline-flex items-center gap-2 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
            <i class="bi bi-list"></i>
            Menu
        </button>
        <span class="text-base font-semibold text-slate-900">Dashboard</span>
    </div>

    <div class="mt-4 hidden gap-4 lg:flex lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <button id="desktopSidebarToggleBtn"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:border-slate-300 hover:bg-slate-100"
                title="Toggle sidebar">
                <i class="bi bi-layout-sidebar text-base"></i>
            </button>
            <div class="space-y-2">
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-[#d4a020]">Dashboard</p>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-slate-900">Monitoring Data Hidroponik</h1>
                    @isset($selectedFarm)
                        <span class="rounded-full bg-[#ffce54]/20 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-[#d4a020]">
                            {{ $selectedFarm->name }}
                        </span>
                    @endisset
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            {{-- Farm Switcher --}}
            @if(isset($farms) && $farms->count() > 1)
                <form action="{{ route('dashboard.switch-farm') }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <label for="farm-switcher" class="text-sm font-medium text-slate-500">Farm:</label>
                    <select name="farm_id" id="farm-switcher" onchange="this.form.submit()"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 focus:border-[#ffce54] focus:ring-2 focus:ring-[#ffce54]/20">
                        @foreach($farms as $farm)
                            <option value="{{ $farm->id }}" @selected(isset($selectedFarm) && $farm->id === $selectedFarm->id)>
                                {{ $farm->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- User info --}}
            <div class="flex items-center gap-2 rounded-2xl bg-slate-100 px-3 py-2">
                <i class="bi bi-person-circle text-slate-500"></i>
                <span class="text-sm font-semibold text-slate-700">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </div>
</header>
