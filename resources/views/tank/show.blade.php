@extends('layouts.app')

@section('title', $tank->name)

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 space-y-6 px-4 py-6 lg:px-8 lg:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $tank->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Detail tank dan riwayat monitoring</p>
                    </div>
                    <a href="{{ route('tank.edit', $tank) }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-pencil"></i>
                        Edit Tank
                    </a>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <i class="bi bi-info-circle"></i>
                            Informasi Tank
                        </h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Nama</dt>
                                <dd class="font-medium text-slate-900">{{ $tank->name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Kapasitas</dt>
                                <dd class="font-medium text-slate-900">{{ number_format($tank->capacity_liter, 0) }} L</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Status</dt>
                                <dd>
                                    @if($tank->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-[#cbe273]/20 px-2.5 py-0.5 text-xs font-semibold text-[#7a9e1a]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#cbe273]"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                            Nonaktif
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Dibuat oleh</dt>
                                <dd class="font-medium text-slate-900">{{ $tank->creator->name ?? '-' }}</dd>
                            </div>
                            @if($tank->notes)
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Catatan</dt>
                                    <dd class="max-w-[200px] text-right font-medium text-slate-900">{{ $tank->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </article>

                    <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <i class="bi bi-sliders"></i>
                            Konfigurasi Target
                        </h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Target PPM</dt>
                                <dd class="font-medium text-slate-900">
                                    @if($tank->target_ppm_min && $tank->target_ppm_max)
                                        {{ number_format($tank->target_ppm_min, 0) }} – {{ number_format($tank->target_ppm_max, 0) }} ppm
                                    @else
                                        <span class="text-slate-400">Belum diatur</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Target pH</dt>
                                <dd class="font-medium text-slate-900">
                                    @if($tank->target_ph_min && $tank->target_ph_max)
                                        {{ number_format($tank->target_ph_min, 1) }} – {{ number_format($tank->target_ph_max, 1) }}
                                    @else
                                        <span class="text-slate-400">Belum diatur</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </article>
                </div>

                <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <i class="bi bi-activity"></i>
                        Kondisi Terkini
                    </h3>
                    @if($tank->current_ppm !== null || $tank->current_ph !== null)
                        <dl class="mt-4 grid gap-4 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs text-slate-500">Current PPM</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ number_format($tank->current_ppm, 0) }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs text-slate-500">Current pH</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ number_format($tank->current_ph, 1) }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs text-slate-500">Water Temperature</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ $tank->current_water_temperature ? $tank->current_water_temperature . '°C' : '-' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs text-slate-500">Last Updated</dt>
                                <dd class="mt-1 text-lg font-bold text-slate-900">{{ $tank->last_condition_updated_at ? \Carbon\Carbon::parse($tank->last_condition_updated_at)->format('d M Y H:i') : '-' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="mt-4 text-sm text-slate-400">Belum ada data kondisi tank.</p>
                    @endif
                </article>

                <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <i class="bi bi-clipboard-data"></i>
                        Riwayat Monitoring
                    </h3>
                    @if($monitorings->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs text-slate-500">
                                        <th class="pb-2 pr-4 font-medium">Tanggal</th>
                                        <th class="pb-2 pr-4 font-medium">PPM</th>
                                        <th class="pb-2 pr-4 font-medium">pH</th>
                                        <th class="pb-2 pr-4 font-medium">Suhu</th>
                                        <th class="pb-2 pr-4 font-medium">Oleh</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($monitorings as $m)
                                        <tr class="text-slate-700">
                                            <td class="py-2.5 pr-4">{{ $m->log_date->format('d M Y') }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($m->ppm, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($m->ph, 1) }}</td>
                                            <td class="py-2.5 pr-4">{{ $m->water_temperature ? number_format($m->water_temperature, 1).' °C' : '-' }}</td>
                                            <td class="py-2.5 pr-4">{{ $m->user->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $monitorings->links() }}
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-400">Belum ada data monitoring.</p>
                    @endif
                </article>

                <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <i class="bi bi-droplet-half"></i>
                        Riwayat Penambahan Nutrisi (AB Mix)
                    </h3>
                    @if($nutrientAdditions->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs text-slate-500">
                                        <th class="pb-2 pr-4 font-medium">Tanggal</th>
                                        <th class="pb-2 pr-4 font-medium">PPM Awal</th>
                                        <th class="pb-2 pr-4 font-medium">PPM Akhir</th>
                                        <th class="pb-2 pr-4 font-medium">Nutrisi A (ml)</th>
                                        <th class="pb-2 pr-4 font-medium">Nutrisi B (ml)</th>
                                        <th class="pb-2 pr-4 font-medium">Oleh</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($nutrientAdditions as $n)
                                        <tr class="text-slate-700">
                                            <td class="py-2.5 pr-4">{{ $n->log_date->format('d M Y') }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($n->ppm_before, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($n->ppm_after, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($n->nutrient_a_ml, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($n->nutrient_b_ml, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ $n->user->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $nutrientAdditions->links() }}
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-400">Belum ada data penambahan nutrisi.</p>
                    @endif
                </article>

                <article class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <i class="bi bi-arrow-down-circle"></i>
                        Riwayat pH Down
                    </h3>
                    @if($phDownLogs->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs text-slate-500">
                                        <th class="pb-2 pr-4 font-medium">Tanggal</th>
                                        <th class="pb-2 pr-4 font-medium">pH Awal</th>
                                        <th class="pb-2 pr-4 font-medium">pH Akhir</th>
                                        <th class="pb-2 pr-4 font-medium">pH Down (ml)</th>
                                        <th class="pb-2 pr-4 font-medium">Oleh</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($phDownLogs as $p)
                                        <tr class="text-slate-700">
                                            <td class="py-2.5 pr-4">{{ $p->log_date->format('d M Y') }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($p->ph_before, 1) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($p->ph_after, 1) }}</td>
                                            <td class="py-2.5 pr-4">{{ number_format($p->ph_down_ml, 0) }}</td>
                                            <td class="py-2.5 pr-4">{{ $p->user->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $phDownLogs->links() }}
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-400">Belum ada data pH Down.</p>
                    @endif
                </article>
            </section>
        </main>
    </div>
@endsection
