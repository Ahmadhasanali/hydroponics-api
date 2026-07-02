@extends('layouts.app')

@section('title', 'Daily Monitoring')

@section('content')
    <div class="min-h-screen lg:flex lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Daily Monitoring</h2>
                        <p class="mt-1 text-sm text-slate-500">Riwayat monitoring harian PPM, pH, dan suhu</p>
                    </div>
                    <a href="{{ route('daily-monitoring.create') }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-plus-lg"></i>
                        Input Data
                    </a>
                </div>

                {{-- TODO: implement daily monitoring list with real data --}}
                @if($monitorings->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Data Monitoring</h3>
                        <p class="mt-1 text-sm text-slate-500">Mulai catat data monitoring harian Anda.</p>
                        <a href="{{ route('daily-monitoring.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Input Data
                        </a>
                    </div>
                @else
                    <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Tank</th>
                                    <th class="px-5 py-3">PPM</th>
                                    <th class="px-5 py-3">pH</th>
                                    <th class="px-5 py-3">Suhu</th>
                                    <th class="px-5 py-3">Oleh</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($monitorings as $m)
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="px-5 py-3 font-medium text-slate-900">{{ $m->log_date ? $m->log_date->format('d M Y') : '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $m->tank->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $m->ppm }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $m->ph }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $m->water_temperature }}°C</td>
                                        <td class="px-5 py-3 text-slate-500">{{ $m->user->name ?? '—' }}</td>
                                        <td class="px-5 py-3">
                                            {{-- TODO: implement edit/delete actions --}}
                                            <span class="text-xs text-slate-400 italic">TODO</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $monitorings->links() }}
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
