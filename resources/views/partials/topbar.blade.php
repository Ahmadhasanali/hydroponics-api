<header class="border-b border-slate-200/80 bg-white px-4 py-4 shadow-sm shadow-slate-900/5 lg:px-6">
    <div class="flex items-center justify-between gap-4 lg:hidden">
        <button id="openSidebarBtn" class="inline-flex items-center gap-2 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
            <i class="bi bi-list"></i>
            Menu
        </button>
        <span class="text-base font-semibold text-slate-900">Dashboard</span>
    </div>

    <div class="mt-4 hidden gap-4 lg:flex lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            {{-- Desktop sidebar toggle button --}}
            <button id="desktopSidebarToggleBtn"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:border-slate-300 hover:bg-slate-100"
                title="Toggle sidebar" aria-label="Toggle sidebar">
                <i class="bi bi-layout-sidebar text-base"></i>
            </button>
            <div class="space-y-2">
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-emerald-700">Dashboard</p>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-slate-900">Monitoring Data Hydroponik</h1>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Realtime</span>
                </div>
                <p class="max-w-2xl text-sm leading-6 text-slate-500">Lihat ringkasan PPM, pH, level air, dan konsumsi nutrisi untuk menjaga kualitas tanam.</p>
            </div>
        </div>


        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <form action="{{ route('logout') }}" method="POST" class="inline-block">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-3xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                    <i class="bi bi-box-arrow-right"></i>
                    Keluar
                </button>
            </form>
            <button class="inline-flex items-center gap-2 rounded-3xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                <i class="bi bi-plus-lg"></i>
                Tambah Catatan
            </button>
        </div>
    </div>

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <label class="relative w-full max-w-xl text-slate-500">
            <span class="sr-only">Cari monitoring</span>
            <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" placeholder="Cari tank, pH, atau PPM..." class="w-full rounded-full border border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-200" />
        </label>
        <div class="flex flex-wrap gap-3">
            <button class="rounded-3xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">Export CSV</button>
            <button class="rounded-3xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Refresh Data</button>
        </div>
    </div>
</header>
