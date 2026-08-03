@extends('layouts.staff')

@section('title', 'Edit Data pH Down')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('staff.ph-down.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
            <i class="bi bi-arrow-left"></i>
            Kembali
        </a>

        <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">Edit Penurunan pH (pH Down)</h2>
            <p class="mt-1 text-sm text-slate-500">Perbarui pH sebelum & sesudah beserta dosis pH Down.</p>

            <form action="{{ route('staff.ph-down.update', $phDownLog) }}" method="POST" class="mt-6 space-y-5">
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
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="log_date" class="block text-sm font-semibold text-slate-700">Tanggal</label>
                    <input type="date" name="log_date" id="log_date" value="{{ old('log_date', $phDownLog->log_date?->format('Y-m-d')) }}" required
                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    @error('log_date')
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ph_before" class="block text-sm font-semibold text-slate-700">pH Sebelum</label>
                        <input type="number" name="ph_before" id="ph_before" step="0.01" min="0" max="14" value="{{ old('ph_before', $phDownLog->ph_before) }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="7.5">
                        @error('ph_before')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="ph_after" class="block text-sm font-semibold text-slate-700">pH Sesudah</label>
                        <input type="number" name="ph_after" id="ph_after" step="0.01" min="0" max="14" value="{{ old('ph_after', $phDownLog->ph_after) }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="6.5">
                        @error('ph_after')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="ph_down_ml" class="block text-sm font-semibold text-slate-700">Dosis pH Down (ml)</label>
                        <input type="number" name="ph_down_ml" id="ph_down_ml" step="0.01" min="0" max="1000" value="{{ old('ph_down_ml', $phDownLog->ph_down_ml) }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20" placeholder="20">
                        @error('ph_down_ml')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                    <textarea name="notes" id="notes" rows="3"
                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Opsional">{{ old('notes', $phDownLog->notes) }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-floppy"></i>
                        Simpan
                    </button>
                    <a href="{{ route('staff.ph-down.index') }}"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
