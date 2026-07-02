@extends('layouts.app')

@section('title', 'Edit Farm')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('farm.index') }}"
                        class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div
                        class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Edit Farm</h2>

                        {{-- TODO: implement farm update — form action, PUT method, validation --}}
                        <form action="{{ route('farm.update', $farm) }}" method="POST" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Nama Farm</label>
                                <input type="text" name="name" id="name" value="{{ $farm->name ?? '' }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Contoh: Farm Hidroponik Lembang">
                            </div>

                            <div>
                                <label for="address" class="block text-sm font-semibold text-slate-700">Alamat</label>
                                <input type="text" name="address" id="address" value="{{ $farm->address ?? '' }}"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Alamat lengkap farm">
                            </div>

                            <div>
                                <label for="description"
                                    class="block text-sm font-semibold text-slate-700">Deskripsi</label>
                                <textarea name="description" id="description" rows="3"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                                    placeholder="Deskripsi singkat tentang farm">{{ $farm->description ?? '' }}</textarea>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-check-lg"></i>
                                    Simpan Perubahan
                                </button>
                                <a href="{{ route('farm.index') }}"
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
