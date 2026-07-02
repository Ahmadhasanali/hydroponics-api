@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="min-h-screen lg:flex lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                @if(!$selectedFarm)
                    {{-- Empty state: no farms --}}
                    <div class="flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54]/20 text-2xl text-[#d4a020]">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <h2 class="mt-6 text-xl font-semibold text-slate-900">Belum Ada Farm</h2>
                        <p class="mt-2 max-w-md text-sm text-slate-500">Anda belum terdaftar di farm manapun. Buat farm baru untuk memulai monitoring hidroponik.</p>
                        <a href="{{ route('farm.create') }}"
                            class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Buat Farm Baru
                        </a>
                    </div>
                @elseif($tanks->isEmpty())
                    {{-- Empty state: no tanks --}}
                    <div class="flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54]/20 text-2xl text-[#d4a020]">
                            <i class="bi bi-water"></i>
                        </div>
                        <h2 class="mt-6 text-xl font-semibold text-slate-900">Belum Ada Tank</h2>
                        <p class="mt-2 max-w-md text-sm text-slate-500">Farm <strong>{{ $selectedFarm->name }}</strong> belum memiliki tank. Tambahkan tank untuk mulai monitoring.</p>
                        <a href="{{ route('farm.index') }}"
                            class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Tambah Tank
                        </a>
                    </div>
                @else
                    {{-- Stats Overview --}}
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <x-dashboard.stat-card
                            title="Total Tank"
                            value="{{ $stats['total_tanks'] }}"
                            description="Jumlah tank terdaftar"
                            icon="bi bi-water"
                            icon-bg="bg-[#ffce54]/15"
                            icon-text="text-[#d4a020]"
                        />
                        <x-dashboard.stat-card
                            title="Tank Aktif"
                            value="{{ $stats['active_tanks'] }}"
                            description="Tank dalam status aktif"
                            icon="bi bi-check-circle"
                            icon-bg="bg-[#cbe273]/15"
                            icon-text="text-[#a3c44a]"
                        />
                        <x-dashboard.stat-card
                            title="Rata-rata PPM"
                            value="{{ $stats['avg_ppm'] ?? '—' }}"
                            description="PPM rata-rata semua tank"
                            icon="bi bi-droplet-half"
                            icon-bg="bg-[#4fc3f7]/15"
                            icon-text="text-[#4fc3f7]"
                        />
                        <x-dashboard.stat-card
                            title="Rata-rata pH"
                            value="{{ $stats['avg_ph'] ?? '—' }}"
                            description="pH rata-rata semua tank"
                            icon="bi bi-flask-fill"
                            icon-bg="bg-[#ffce54]/15"
                            icon-text="text-[#d4a020]"
                        />
                        <x-dashboard.stat-card
                            title="Rata-rata Suhu"
                            value="{{ $stats['avg_temp'] ? $stats['avg_temp'].'°C' : '—' }}"
                            description="Suhu air rata-rata"
                            icon="bi bi-thermometer-half"
                            icon-bg="bg-orange-100"
                            icon-text="text-orange-600"
                        />
                    </div>

                    {{-- Tank List & Monitoring Summary --}}
                    <div class="mt-8 grid gap-6 xl:grid-cols-3">
                        {{-- Tank cards --}}
                        <div class="xl:col-span-2 space-y-4">
                            <h2 class="text-lg font-semibold text-slate-900">Daftar Tank</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach($tanks as $tank)
                                    @php
                                        $monitoring = $latestMonitorings->get($tank->id);
                                    @endphp
                                    <article class="rounded-[2rem] border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-900/5">
                                        <div class="flex items-center justify-between">
                                            <h3 class="font-semibold text-slate-900">{{ $tank->name }}</h3>
                                            <span class="inline-flex h-2.5 w-2.5 rounded-full {{ $tank->is_active ? 'bg-[#cbe273]' : 'bg-slate-300' }}"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ number_format($tank->capacity_liter, 0) }} L</p>
                                        @if($monitoring)
                                            <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center text-xs">
                                                <div>
                                                    <p class="text-slate-400">PPM</p>
                                                    <p class="font-semibold text-slate-900">{{ $monitoring->ppm }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-400">pH</p>
                                                    <p class="font-semibold text-slate-900">{{ $monitoring->ph }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-400">Suhu</p>
                                                    <p class="font-semibold text-slate-900">{{ $monitoring->water_temperature }}°C</p>
                                                </div>
                                            </div>
                                        @else
                                            <p class="mt-3 text-xs text-slate-400 italic">Belum ada data monitoring</p>
                                        @endif
                                        <p class="mt-2 text-xs text-slate-400">{{ $tank->notes ? Str::limit($tank->notes, 60) : '' }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        {{-- Right column: Action buttons + Activity Logs --}}
                        <div class="space-y-6">
                            {{-- Quick Actions --}}
                            <div>
                                <h2 class="mb-3 text-lg font-semibold text-slate-900">Aksi Cepat</h2>
                                <div class="space-y-3">
                                    <a href="#"
                                        class="flex items-center gap-3 rounded-[2rem] border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-900/5 transition hover:bg-[#cbe273]/5 hover:border-[#cbe273]/30">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#cbe273]/15 text-[#a3c44a]">
                                            <i class="bi bi-droplet"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Tambah AB Mix</p>
                                            <p class="text-xs text-slate-500">Catat penambahan nutrisi</p>
                                        </div>
                                        <i class="bi bi-chevron-right ml-auto text-slate-400"></i>
                                    </a>

                                    <a href="#"
                                        class="flex items-center gap-3 rounded-[2rem] border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5 hover:border-[#ffce54]/30">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54]/15 text-[#d4a020]">
                                            <i class="bi bi-flask-fill"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Tambah pH Down</p>
                                            <p class="text-xs text-slate-500">Catat penurunan pH</p>
                                        </div>
                                        <i class="bi bi-chevron-right ml-auto text-slate-400"></i>
                                    </a>

                                    <a href="#"
                                        class="flex items-center gap-3 rounded-[2rem] border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-900/5 transition hover:bg-[#4fc3f7]/5 hover:border-[#4fc3f7]/30">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                                            <i class="bi bi-clipboard-data"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Daily Monitoring</p>
                                            <p class="text-xs text-slate-500">Input data harian</p>
                                        </div>
                                        <i class="bi bi-chevron-right ml-auto text-slate-400"></i>
                                    </a>
                                </div>
                            </div>

                            {{-- Activity Logs --}}
                            <div>
                                <h2 class="mb-3 text-lg font-semibold text-slate-900">Aktivitas Terbaru</h2>
                                <div class="rounded-[2rem] border border-slate-200/80 bg-white shadow-sm shadow-slate-900/5">
                                    @if($activityLogs->isEmpty())
                                        <div class="flex flex-col items-center px-4 py-8 text-center">
                                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                                <i class="bi bi-clock-history"></i>
                                            </div>
                                            <p class="mt-3 text-sm text-slate-500">Belum ada aktivitas</p>
                                        </div>
                                    @else
                                        <div class="divide-y divide-slate-100">
                                            @foreach($activityLogs as $log)
                                                <div class="flex items-start gap-3 px-4 py-3">
                                                    <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                                        {{ strtoupper(substr($log->user->name ?? '?', 0, 2)) }}
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm text-slate-700">
                                                            <span class="font-semibold">{{ $log->user->name ?? 'System' }}</span>
                                                            {{ $log->description }}
                                                        </p>
                                                        <p class="mt-0.5 text-xs text-slate-400">{{ $log->created_at ? $log->created_at->diffForHumans() : '' }}</p>
                                                    </div>
                                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $log->action }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
