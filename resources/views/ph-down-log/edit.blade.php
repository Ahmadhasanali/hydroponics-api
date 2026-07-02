@extends('layouts.app')

@section('title', 'Edit pH Down')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('ph-down-log.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Edit pH Down</h2>
                        <p class="mt-1 text-sm text-slate-500">Ubah data penurunan pH.</p>

                        <form action="{{ route('ph-down-log.update', $phDownLog) }}" method="POST" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="tank_id" class="block text-sm font-semibold text-slate-700">Tank</label>
                                <select name="tank_id" id="tank_id" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    <option value="">Pilih tank</option>
                                    @foreach($tanks as $tank)
                                        <option value="{{ $tank->id }}" @selected(old('tank_id', $phDownLog->tank_id) == $tank->id)>{{ $tank->name }} ({{ number_format($tank->capacity_liter, 0) }} L)</option>
                                    @endforeach
                                </select>
                                @error('tank_id')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="log_date" class="block text-sm font-semibold text-slate-700">Tanggal</label>
                                <input type="date" name="log_date" id="log_date" required value="{{ old('log_date', $phDownLog->log_date?->format('Y-m-d')) }}"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                @error('log_date')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="ph_before" class="block text-sm font-semibold text-slate-700">pH Sebelum</label>
                                    <input type="number" name="ph_before" id="ph_before" required step="any" min="0" max="14" value="{{ old('ph_before', $phDownLog->ph_before) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('ph_before')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="ph_after" class="block text-sm font-semibold text-slate-700">pH Sesudah</label>
                                    <input type="number" name="ph_after" id="ph_after" required step="any" min="0" max="14" value="{{ old('ph_after', $phDownLog->ph_after) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('ph_after')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="ph_down_ml" class="block text-sm font-semibold text-slate-700">pH Down Digunakan (ml)</label>
                                <input type="number" name="ph_down_ml" id="ph_down_ml" required step="any" min="0" max="1000" value="{{ old('ph_down_ml', $phDownLog->ph_down_ml) }}"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                @error('ph_down_ml')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">{{ old('notes', $phDownLog->notes) }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-check-lg"></i>
                                    Perbarui
                                </button>
                                <a href="{{ route('ph-down-log.index') }}"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
