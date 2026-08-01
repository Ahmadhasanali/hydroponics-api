@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl space-y-6">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6">
                        <div class="flex items-center gap-4">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54] text-xl text-[#1a1c1e]">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <div>
                                <h1 class="text-xl font-semibold text-slate-900">{{ $user->name }}</h1>
                            </div>
                        </div>

                        @if ($farms->isNotEmpty())
                            <div class="mt-5 space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Farm Saya</p>
                                @foreach ($farms as $farm)
                                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                                        <span class="text-sm font-semibold text-slate-700">{{ $farm->name }}</span>
                                        <span class="rounded-full bg-[#ffce54]/20 px-2.5 py-0.5 text-xs font-semibold text-[#d4a020]">
                                            {{ ucfirst($farm->pivot->role) }} · {{ $farm->tanks_count }} tank
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-4">
                        <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Menu Lainnya</p>
                        <div class="grid gap-1">
                            <a href="{{ route('farm.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-buildings-fill text-base text-slate-400"></i> Farm
                            </a>
                            <a href="{{ route('tank.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-water text-base text-slate-400"></i> Tank
                            </a>
                            <a href="{{ route('reports.monitoring') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-bar-chart-line text-base text-slate-400"></i> Laporan Monitoring
                            </a>
                            <a href="{{ route('reports.nutrient') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-pie-chart text-base text-slate-400"></i> Laporan AB Mix
                            </a>
                            <a href="{{ route('reports.ph-down') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-graph-down text-base text-slate-400"></i> Laporan pH Down
                            </a>
                            <a href="{{ route('activity-logs.index') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-[#ffce54]/10">
                                <i class="bi bi-clock-history text-base text-slate-400"></i> Activity Logs
                            </a>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-4">
                        <form method="POST" action="{{ route('logout') }}" class="js-logout-form w-full">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold text-red-600 hover:bg-red-50">
                                <i class="bi bi-box-arrow-right text-base"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
