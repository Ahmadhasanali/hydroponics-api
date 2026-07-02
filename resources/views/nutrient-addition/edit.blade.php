@extends('layouts.app')

@section('title', 'Edit AB Mix')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('nutrient-addition.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Edit AB Mix</h2>
                        <p class="mt-1 text-sm text-slate-500">Ubah data penambahan nutrisi AB Mix.</p>

                        <form action="{{ route('nutrient-addition.update', $nutrientAddition) }}" method="POST" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="tank_id" class="block text-sm font-semibold text-slate-700">Tank</label>
                                <select name="tank_id" id="tank_id" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    <option value="">Pilih tank</option>
                                    @foreach($tanks as $tank)
                                        <option value="{{ $tank->id }}" @selected(old('tank_id', $nutrientAddition->tank_id) == $tank->id)>{{ $tank->name }} ({{ number_format($tank->capacity_liter, 0) }} L)</option>
                                    @endforeach
                                </select>
                                @error('tank_id')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="log_date" class="block text-sm font-semibold text-slate-700">Tanggal</label>
                                <input type="date" name="log_date" id="log_date" required value="{{ old('log_date', $nutrientAddition->log_date?->format('Y-m-d')) }}"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                @error('log_date')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="ppm_before" class="block text-sm font-semibold text-slate-700">PPM Sebelum</label>
                                    <input type="number" name="ppm_before" id="ppm_before" required step="any" min="0" max="3000" value="{{ old('ppm_before', $nutrientAddition->ppm_before) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('ppm_before')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="ppm_after" class="block text-sm font-semibold text-slate-700">PPM Sesudah</label>
                                    <input type="number" name="ppm_after" id="ppm_after" required step="any" min="0" max="3000" value="{{ old('ppm_after', $nutrientAddition->ppm_after) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('ppm_after')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="nutrient_a_ml" class="block text-sm font-semibold text-slate-700">Nutrisi A (ml)</label>
                                    <input type="number" name="nutrient_a_ml" id="nutrient_a_ml" required step="any" min="0" max="10000" value="{{ old('nutrient_a_ml', $nutrientAddition->nutrient_a_ml) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('nutrient_a_ml')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="nutrient_b_ml" class="block text-sm font-semibold text-slate-700">Nutrisi B (ml)</label>
                                    <input type="number" name="nutrient_b_ml" id="nutrient_b_ml" required step="any" min="0" max="10000" value="{{ old('nutrient_b_ml', $nutrientAddition->nutrient_b_ml) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    @error('nutrient_b_ml')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">{{ old('notes', $nutrientAddition->notes) }}</textarea>
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
                                <a href="{{ route('nutrient-addition.index') }}"
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
