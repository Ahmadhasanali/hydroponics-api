@extends('layouts.app')

@section('title', 'Buat Reminder')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('farm.reminders.index', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Buat Reminder</h2>
                        <p class="mt-1 text-sm text-slate-500">Buat pengingat jadwal untuk dirimu atau member kebun.</p>

                        <form action="{{ route('farm.reminders.store', $farm) }}" method="POST" class="mt-6 space-y-5">
                            @csrf

                            <div>
                                <label for="title" class="block text-sm font-semibold text-slate-700">Judul</label>
                                <input type="text" name="title" id="title" value="{{ old('title') }}" required maxlength="255"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Tambahkan AB Mix">
                                @error('title')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="body" class="block text-sm font-semibold text-slate-700">Deskripsi</label>
                                <textarea name="body" id="body" rows="3" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Tambahkan AB mix ke tank utama">{{ old('body') }}</textarea>
                                @error('body')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="starts_at" class="block text-sm font-semibold text-slate-700">Tanggal & Waktu</label>
                                <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at') }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                @error('starts_at')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Perulangan</label>
                                <div class="mt-2 space-y-2">
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="recurrence[type]" value="none" @checked(old('recurrence.type', 'none') === 'none') class="accent-[#ffce54]">
                                        Sekali saja
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="recurrence[type]" value="interval" @checked(old('recurrence.type') === 'interval') class="accent-[#ffce54]">
                                        Setiap beberapa hari
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="recurrence[type]" value="weekly" @checked(old('recurrence.type') === 'weekly') class="accent-[#ffce54]">
                                        Setiap minggu di hari tertentu
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="recurrence[type]" value="monthly" @checked(old('recurrence.type') === 'monthly') class="accent-[#ffce54]">
                                        Setiap bulan di tanggal tertentu
                                    </label>
                                </div>

                                <div class="mt-3">
                                    <label for="every_days" class="block text-sm font-semibold text-slate-700">Setiap berapa hari</label>
                                    <input type="number" name="recurrence[every_days]" id="every_days" min="1" value="{{ old('recurrence.every_days', 1) }}"
                                        class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                </div>

                                <div class="mt-3">
                                    <span class="block text-sm font-semibold text-slate-700">Hari dalam seminggu</span>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach(['mon' => 'Senin', 'tue' => 'Selasa', 'wed' => 'Rabu', 'thu' => 'Kamis', 'fri' => 'Jumat', 'sat' => 'Sabtu', 'sun' => 'Minggu'] as $key => $label)
                                            <label class="flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                                <input type="checkbox" name="recurrence[days_of_week][]" value="{{ $key }}"
                                                    @checked(in_array($key, old('recurrence.days_of_week', []), true))
                                                    class="accent-[#ffce54]">
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <span class="block text-sm font-semibold text-slate-700">Tanggal dalam bulan</span>
                                    <div class="mt-2 grid grid-cols-8 gap-1.5">
                                        @for($day = 1; $day <= 31; $day++)
                                            <label class="flex items-center justify-center rounded-xl bg-slate-100 py-1.5 text-xs font-semibold text-slate-600">
                                                <input type="checkbox" name="recurrence[days_of_month][]" value="{{ $day }}"
                                                    @checked(in_array($day, old('recurrence.days_of_month', []), true))
                                                    class="accent-[#ffce54]">
                                                <span class="ml-1">{{ $day }}</span>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                                @error('recurrence.type')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="advance_notify_minutes" class="block text-sm font-semibold text-slate-700">Pengingat Awal</label>
                                <select name="advance_notify_minutes" id="advance_notify_minutes"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                                    <option value="">Tanpa pengingat awal</option>
                                    <option value="30" @selected(old('advance_notify_minutes') == 30)>30 menit sebelumnya</option>
                                    <option value="60" @selected(old('advance_notify_minutes') == 60)>1 jam sebelumnya</option>
                                    <option value="1440" @selected(old('advance_notify_minutes') == 1440)>1 hari sebelumnya</option>
                                </select>
                                @error('advance_notify_minutes')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Target</label>
                                <div class="mt-2 space-y-2">
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="target_mode" value="self" @checked(old('target_mode', 'self') === 'self') class="accent-[#ffce54]">
                                        Hanya saya
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="target_mode" value="all" @checked(old('target_mode') === 'all') class="accent-[#ffce54]">
                                        Semua member kebun
                                    </label>
                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                        <input type="radio" name="target_mode" value="specific" @checked(old('target_mode') === 'specific') class="accent-[#ffce54]">
                                        User/staff tertentu
                                    </label>
                                </div>
                                @error('target_mode')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="specific-targets" class="hidden">
                                <label class="block text-sm font-semibold text-slate-700">Pilih Target</label>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @foreach($eligible as $target)
                                        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm">
                                            <input type="checkbox" name="target_ids[]" value="{{ $target['id'] }}"
                                                @checked(in_array($target['id'], old('target_ids', []), true))
                                                class="accent-[#ffce54]">
                                            {{ $target['name'] }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('target_ids')
                                    <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-floppy"></i>
                                    Simpan
                                </button>
                                <a href="{{ route('farm.reminders.index', $farm) }}"
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
        (function () {
            const modeRadios = document.querySelectorAll('input[name="target_mode"]');
            const specificBox = document.getElementById('specific-targets');

            const toggle = () => {
                const checked = document.querySelector('input[name="target_mode"]:checked');
                specificBox.classList.toggle('hidden', !checked || checked.value !== 'specific');
            };

            modeRadios.forEach((radio) => radio.addEventListener('change', toggle));
            toggle();
        })();
    </script>
@endsection

