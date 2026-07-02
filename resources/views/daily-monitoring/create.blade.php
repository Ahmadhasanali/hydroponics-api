@extends('layouts.app')

@section('title', 'Input Daily Monitoring')

@section('content')
    <div class="min-h-screen lg:flex lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('daily-monitoring.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Input Daily Monitoring</h2>
                        <p class="mt-1 text-sm text-slate-500">Catat PPM, pH, dan suhu air.</p>

                        <form action="{{ route('daily-monitoring.store') }}" method="POST" class="mt-6 space-y-5" id="monitoring-form">
                            @csrf

                            <div>
                                <label for="tank_id" class="block text-sm font-semibold text-slate-700">Tank</label>
                                <select name="tank_id" id="tank_id" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    <option value="">Pilih tank</option>
                                    @foreach($tanks as $tank)
                                        <option value="{{ $tank->id }}"
                                            data-target-ppm-min="{{ $tank->target_ppm_min }}"
                                            data-target-ppm-max="{{ $tank->target_ppm_max }}"
                                            data-target-ph-min="{{ $tank->target_ph_min }}"
                                            data-target-ph-max="{{ $tank->target_ph_max }}"
                                            @selected(old('tank_id', request('tank_id')) == $tank->id)>{{ $tank->name }} ({{ number_format($tank->capacity_liter, 0) }} L)</option>
                                    @endforeach
                                </select>
                                @error('tank_id')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="log_date" class="block text-sm font-semibold text-slate-700">Tanggal Monitoring</label>
                                <input type="date" name="log_date" id="log_date" value="{{ old('log_date', date('Y-m-d')) }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('log_date') border-red-300 @enderror">
                                @error('log_date')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="ppm" class="block text-sm font-semibold text-slate-700">PPM</label>
                                    <input type="number" name="ppm" id="ppm" step="0.01" min="0" max="3000" value="{{ old('ppm') }}" required
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('ppm') border-red-300 @enderror"
                                        placeholder="700">
                                    @error('ppm')
                                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="ph" class="block text-sm font-semibold text-slate-700">pH</label>
                                    <input type="number" name="ph" id="ph" step="0.01" min="0" max="14" value="{{ old('ph') }}" required
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('ph') border-red-300 @enderror"
                                        placeholder="6.5">
                                    @error('ph')
                                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="water_temperature" class="block text-sm font-semibold text-slate-700">Suhu Air (°C)</label>
                                    <input type="number" name="water_temperature" id="water_temperature" step="0.01" value="{{ old('water_temperature') }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('water_temperature') border-red-300 @enderror"
                                        placeholder="25">
                                    @error('water_temperature')
                                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                                <textarea name="notes" id="notes" rows="3"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('notes') border-red-300 @enderror"
                                    placeholder="Opsional">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" id="submit-btn"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-floppy"></i>
                                    Simpan
                                </button>
                                <a href="{{ route('daily-monitoring.index') }}"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

<script>
document.getElementById('monitoring-form')?.addEventListener('submit', function (e) {
    const tankSelect = document.getElementById('tank_id');
    const selected = tankSelect.options[tankSelect.selectedIndex];
    const ppmMin = parseFloat(selected?.dataset?.targetPpmMin);
    const ppmMax = parseFloat(selected?.dataset?.targetPpmMax);
    const phMin = parseFloat(selected?.dataset?.targetPhMin);
    const phMax = parseFloat(selected?.dataset?.targetPhMax);

    if (!ppmMin && !ppmMax && !phMin && !phMax) return;

    const ppm = parseFloat(document.getElementById('ppm')?.value);
    const ph = parseFloat(document.getElementById('ph')?.value);

    const issues = [];

    if (ppm && ppmMin && ppm < ppmMin) issues.push('PPM ' + ppm + ' di bawah target minimum ' + ppmMin);
    if (ppm && ppmMax && ppm > ppmMax) issues.push('PPM ' + ppm + ' di atas target maksimum ' + ppmMax);
    if (ph && phMin && ph < phMin) issues.push('pH ' + ph + ' di bawah target minimum ' + phMin);
    if (ph && phMax && ph > phMax) issues.push('pH ' + ph + ' di atas target maksimum ' + phMax);

    if (issues.length && !confirm('Peringatan:\n' + issues.join('\n') + '\n\nLanjutkan menyimpan?')) {
        e.preventDefault();
    }
});
</script>
@endsection