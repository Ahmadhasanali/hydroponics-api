@extends('layouts.app')

@section('title', 'pH Down')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">pH Down</h2>
                        <p class="mt-1 text-sm text-slate-500">Riwayat penurunan pH</p>
                    </div>
                    <a href="{{ route('ph-down-log.create') }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-plus-lg"></i>
                        Tambah pH Down
                    </a>
                </div>

                @if($logs->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54]/15 text-[#d4a020]">
                            <i class="bi bi-flask-fill"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Data pH Down</h3>
                        <p class="mt-1 text-sm text-slate-500">Catat penurunan pH pada tank.</p>
                        <a href="{{ route('ph-down-log.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Tambah pH Down
                        </a>
                    </div>
                @else
                    <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Tank</th>
                                    <th class="px-5 py-3">pH Sebelum</th>
                                    <th class="px-5 py-3">pH Sesudah</th>
                                    <th class="px-5 py-3">pH Down (ml)</th>
                                    <th class="px-5 py-3">Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($logs as $log)
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="px-5 py-3 font-medium text-slate-900">{{ $log->log_date ? $log->log_date->format('d M Y') : '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $log->tank->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $log->ph_before }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $log->ph_after }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $log->ph_down_ml }}</td>
                                        <td class="px-5 py-3 text-slate-500">{{ $log->user->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
