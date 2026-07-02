@extends('layouts.app')
@section('title', 'Edit Tank')
@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')
        <main class="flex flex-1 flex-col">
            @include('partials.topbar')
            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('tank.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>
                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Edit Tank</h2>
                        <p class="mt-1 text-sm text-slate-500">Perbarui detail tank.</p>
                        <form action="{{ route('tank.update', $tank) }}" method="POST" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Nama Tank</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $tank->name) }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Contoh: Tank A1">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="capacity_liter" class="block text-sm font-semibold text-slate-700">Kapasitas (Liter)</label>
                                <input type="number" step="0.01" name="capacity_liter" id="capacity_liter" value="{{ old('capacity_liter', $tank->capacity_liter) }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Contoh: 100">
                                @error('capacity_liter')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="target_ppm_min" class="block text-sm font-semibold text-slate-700">Target PPM Min</label>
                                    <input type="number" step="1" name="target_ppm_min" id="target_ppm_min" value="{{ old('target_ppm_min', $tank->target_ppm_min) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                </div>
                                <div>
                                    <label for="target_ppm_max" class="block text-sm font-semibold text-slate-700">Target PPM Max</label>
                                    <input type="number" step="1" name="target_ppm_max" id="target_ppm_max" value="{{ old('target_ppm_max', $tank->target_ppm_max) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="target_ph_min" class="block text-sm font-semibold text-slate-700">Target pH Min</label>
                                    <input type="number" step="0.1" name="target_ph_min" id="target_ph_min" value="{{ old('target_ph_min', $tank->target_ph_min) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                </div>
                                <div>
                                    <label for="target_ph_max" class="block text-sm font-semibold text-slate-700">Target pH Max</label>
                                    <input type="number" step="0.1" name="target_ph_max" id="target_ph_max" value="{{ old('target_ph_max', $tank->target_ph_max) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                </div>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Catatan opsional">{{ old('notes', $tank->notes) }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $tank->is_active) ? 'checked' : '' }}
                                    class="h-5 w-5 rounded-lg border-slate-300 text-[#ffce54] focus:ring-[#ffce54]/20">
                                <label for="is_active" class="text-sm font-semibold text-slate-700">Tank Aktif</label>
                            </div>
                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-check-lg"></i>
                                    Simpan Perubahan
                                </button>
                                <a href="{{ route('tank.index') }}"
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
