@extends('layouts.app')

@section('title', 'Tambah Tank')

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
                        <h2 class="text-lg font-semibold text-slate-900">Tambah Tank Baru</h2>
                        <p class="mt-1 text-sm text-slate-500">Isi detail tank untuk monitoring.</p>

                        {{-- TODO: implement tank store — form action, validation, and CSRF --}}
                        <form action="{{ route('tank.store') }}" method="POST" class="mt-6 space-y-5">
                            @csrf

                            <input type="hidden" name="farm_id" value="{{ $farmId }}">

                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Nama Tank</label>
                                <input type="text" name="name" id="name" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Contoh: Tank A1">
                            </div>

                            <div>
                                <label for="capacity_liter" class="block text-sm font-semibold text-slate-700">Kapasitas (Liter)</label>
                                <input type="number" name="capacity_liter" id="capacity_liter" step="0.01" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="100">
                            </div>

                            <div>
                                <label for="notes" class="block text-sm font-semibold text-slate-700">Catatan</label>
                                <textarea name="notes" id="notes" rows="2"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Catatan tambahan"></textarea>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" id="is_active" value="1" checked
                                    class="h-4 w-4 rounded border-slate-300 text-[#ffce54] focus:ring-[#ffce54]/30">
                                <label for="is_active" class="text-sm text-slate-700">Aktif</label>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-check-lg"></i>
                                    Simpan
                                </button>
                                <a href="{{ route('tank.index') }}"
                                    class="rounded-2xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
