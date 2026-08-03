@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Dashboard {{ $farm->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">Ringkasan kondisi kebun yang ditugaskan.</p>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tank</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['total_tanks'] }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tank Aktif</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $stats['active_tanks'] }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata PPM</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['avg_ppm'] ?? '—' }}</p>
        </div>
        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata pH</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['avg_ph'] ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @if(Route::has('staff.monitoring.create'))
            <a href="{{ route('staff.monitoring.create') }}"
                class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                    <i class="bi bi-thermometer-half"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">Input Monitoring</h3>
                <p class="mt-1 text-xs text-slate-500">Catat PPM, pH, dan suhu harian.</p>
            </a>
        @endif
        @if(Route::has('staff.nutrient.create'))
            <a href="{{ route('staff.nutrient.create') }}"
                class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                    <i class="bi bi-droplet"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">Input AB Mix</h3>
                <p class="mt-1 text-xs text-slate-500">Catat penambahan nutrisi AB Mix.</p>
            </a>
        @endif
        @if(Route::has('staff.ph-down.create'))
            <a href="{{ route('staff.ph-down.create') }}"
                class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:bg-[#ffce54]/5">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                    <i class="bi bi-flask"></i>
                </div>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">Input pH Down</h3>
                <p class="mt-1 text-xs text-slate-500">Catat penurunan pH.</p>
            </a>
        @endif
    </div>
@endsection
