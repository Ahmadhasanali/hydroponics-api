@extends('layouts.app')

@section('title', 'AB Mix')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">AB Mix</h2>
                        <p class="mt-1 text-sm text-slate-500">Riwayat penambahan nutrisi AB Mix</p>
                    </div>
                    <a href="{{ route('nutrient-addition.create') }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-plus-lg"></i>
                        Tambah AB Mix
                    </a>
                </div>

                @if($additions->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#cbe273]/15 text-[#a3c44a]">
                            <i class="bi bi-droplet"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Data AB Mix</h3>
                        <p class="mt-1 text-sm text-slate-500">Catat penambahan nutrisi AB Mix pada tank.</p>
                        <a href="{{ route('nutrient-addition.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Tambah AB Mix
                        </a>
                    </div>
                @else
                    <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Tank</th>
                                    <th class="px-5 py-3">PPM Sebelum</th>
                                    <th class="px-5 py-3">PPM Sesudah</th>
                                    <th class="px-5 py-3">Nutrisi A (ml)</th>
                                    <th class="px-5 py-3">Nutrisi B (ml)</th>
                                    <th class="px-5 py-3">Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($additions as $a)
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="px-5 py-3 font-medium text-slate-900">{{ $a->log_date ? $a->log_date->format('d M Y') : '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $a->tank->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $a->ppm_before }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $a->ppm_after }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $a->nutrient_a_ml }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $a->nutrient_b_ml }}</td>
                                        <td class="px-5 py-3 text-slate-500">{{ $a->user->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $additions->links() }}
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
