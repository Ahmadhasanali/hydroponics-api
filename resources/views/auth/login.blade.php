@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -left-20 top-[-6rem] h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl"></div>
        <div class="absolute bottom-[-8rem] right-[-4rem] h-[28rem] w-[28rem] rounded-full bg-cyan-400/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <div class="text-center">
            <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-300 to-teal-500 text-2xl text-slate-950 shadow-lg shadow-emerald-400/20">
                <i class="bi bi-droplet-half"></i>
            </div>
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white">Selamat Datang</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">Masuk untuk mengelola sistem hidroponik Anda</p>
        </div>

        @if ($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
                <div class="flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="font-medium">Silakan periksa kembali input Anda.</span>
                </div>
            </div>
        @endif

        <form action="{{ url('/login') }}" method="POST" id="loginForm" class="mt-8 space-y-5" novalidate>
            @csrf

            <div>
                <label for="username" class="mb-2 block text-sm font-semibold text-slate-200">Username</label>
                <div class="group flex items-center rounded-2xl border border-white/10 bg-white/5 transition focus-within:border-emerald-400/50 focus-within:bg-white/10 focus-within:ring-4 focus-within:ring-emerald-400/10 {{ $errors->has('username') ? 'border-rose-400/40 focus-within:border-rose-400/60 focus-within:ring-rose-400/10' : '' }}">
                    <span class="pl-4 text-slate-400">
                        <i class="bi bi-person"></i>
                    </span>
                    <input
                        type="text"
                        class="w-full bg-transparent px-3 py-4 text-sm text-white placeholder:text-slate-500 focus:outline-none"
                        id="username"
                        name="username"
                        value="{{ old('username') }}"
                        placeholder="Masukkan username Anda"
                        autocomplete="username"
                        required
                        enterkeyhint="next"
                    >
                </div>
                @error('username')
                    <p class="mt-2 flex items-center gap-2 text-sm text-rose-200">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label for="password" class="block text-sm font-semibold text-slate-200">Kata Sandi</label>
                </div>
                <div class="group flex items-center rounded-2xl border border-white/10 bg-white/5 transition focus-within:border-emerald-400/50 focus-within:bg-white/10 focus-within:ring-4 focus-within:ring-emerald-400/10 {{ $errors->has('password') ? 'border-rose-400/40 focus-within:border-rose-400/60 focus-within:ring-rose-400/10' : '' }}">
                    <span class="pl-4 text-slate-400">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input
                        type="password"
                        class="w-full bg-transparent px-3 py-4 text-sm text-white placeholder:text-slate-500 focus:outline-none"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        enterkeyhint="done"
                    >
                    <button
                        type="button"
                        class="pr-4 text-slate-400 transition hover:text-white"
                        id="togglePassword"
                        aria-pressed="false"
                        aria-label="Tampilkan kata sandi"
                    >
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <p class="mt-2 flex items-center gap-2 text-sm text-rose-200 hidden" id="passwordClientError" aria-live="polite">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Kata sandi wajib diisi.</span>
                </p>
                @error('password')
                    <p class="mt-2 flex items-center gap-2 text-sm text-rose-200">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-4">
                <label class="inline-flex cursor-pointer items-center gap-3 text-sm text-slate-300">
                    <input class="h-4 w-4 rounded border-white/20 bg-white/10 text-emerald-500 focus:ring-emerald-400/40" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span>Ingat Saya</span>
                </label>
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-300 to-teal-400 px-4 py-4 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-emerald-400/20 disabled:cursor-not-allowed disabled:bg-slate-500 disabled:text-slate-800 disabled:shadow-none" id="btnSubmit">
                Masuk Sekarang
            </button>
        </form>
    </div>
</div>
@endsection
