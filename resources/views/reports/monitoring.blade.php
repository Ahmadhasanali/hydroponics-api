@extends('layouts.app')

@section('title', 'Laporan Monitoring')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-5xl">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Laporan Monitoring</h2>
                        <p class="mt-1 text-sm text-slate-500">Filter dan lihat agregasi data monitoring PPM & pH</p>
                    </div>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <form method="GET" action="{{ route('reports.monitoring') }}" class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <label for="tank_id" class="block text-sm font-semibold text-slate-700">Tank</label>
                                <select name="tank_id" id="tank_id" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    <option value="">Pilih Tank</option>
                                    @foreach($tanks as $tank)
                                        <option value="{{ $tank->id }}" {{ $tankId == $tank->id ? 'selected' : '' }}>{{ $tank->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex-1 min-w-[180px]">
                                <label for="start_date" class="block text-sm font-semibold text-slate-700">Tanggal Mulai</label>
                                <input type="date" name="start_date" id="start_date" value="{{ $startDate ?? '' }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                            </div>

                            <div class="flex-1 min-w-[180px]">
                                <label for="end_date" class="block text-sm font-semibold text-slate-700">Tanggal Akhir</label>
                                <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                            </div>

                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                <i class="bi bi-funnel"></i>
                                Filter
                            </button>
                        </form>
                    </div>

                    @if($aggregates)
                        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Data</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $aggregates->count }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata PPM</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($aggregates->avg_ppm, 2) }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">PPM Tertinggi</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($aggregates->highest_ppm, 2) }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">PPM Terendah</p>
                                <p class="mt-2 text-2xl font-bold text-red-600">{{ number_format($aggregates->lowest_ppm, 2) }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata pH</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($aggregates->avg_ph, 2) }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">pH Tertinggi</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($aggregates->highest_ph, 2) }}</p>
                            </div>

                            <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">pH Terendah</p>
                                <p class="mt-2 text-2xl font-bold text-red-600">{{ number_format($aggregates->lowest_ph, 2) }}</p>
                            </div>
                        </div>
                    @elseif(request()->has('tank_id'))
                        <div class="mt-8 flex flex-col items-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                <i class="bi bi-clipboard-data"></i>
                            </div>
                            <h3 class="mt-5 text-lg font-semibold text-slate-900">Tidak Ada Data</h3>
                            <p class="mt-1 text-sm text-slate-500">Tidak ada data monitoring untuk filter yang dipilih.</p>
                        </div>
                    @endif

                    @if(!$aggregates && !request()->has('tank_id'))
                        <div class="mt-8 flex flex-col items-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                <i class="bi bi-funnel"></i>
                            </div>
                            <h3 class="mt-5 text-lg font-semibold text-slate-900">Pilih Filter</h3>
                            <p class="mt-1 text-sm text-slate-500">Pilih tank dan rentang tanggal untuk melihat laporan.</p>
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </div>
@endsection
