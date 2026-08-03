@extends('layouts.app')

@section('title', 'Tambah Petugas Lapangan')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-2xl">
                    <a href="{{ route('farm.members.index', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <h2 class="text-lg font-semibold text-slate-900">Tambah Petugas Lapangan</h2>
                        <p class="mt-1 text-sm text-slate-500">Buat akun petugas untuk kebun <strong>{{ $farm->name }}</strong>.</p>

                        <form action="{{ route('farm.members.staff-store', $farm) }}" method="POST" class="mt-6 space-y-5">
                            @csrf

                            <div>
                                <label for="name" class="block text-sm font-semibold text-slate-700">Nama Petugas</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('name') border-red-300 @enderror">
                                @error('name')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="username" class="block text-sm font-semibold text-slate-700">Username</label>
                                <input type="text" name="username" id="username" value="{{ old('username') }}" required
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('username') border-red-300 @enderror">
                                @error('username')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                                <input type="password" name="password" id="password" required minlength="8"
                                    class="mt-1.5 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20 @error('password') border-red-300 @enderror">
                                @error('password')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                    <i class="bi bi-person-plus"></i>
                                    Buat Akun
                                </button>
                                <a href="{{ route('farm.members.index', $farm) }}"
                                    class="rounded-2xl px-6 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
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
