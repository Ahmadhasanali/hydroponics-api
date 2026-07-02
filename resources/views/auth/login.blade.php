@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -left-20 top-[-6rem] h-96 w-96 rounded-full bg-[#ffce54]/10 blur-3xl"></div>
        <div class="absolute bottom-[-8rem] right-[-4rem] h-[28rem] w-[28rem] rounded-full bg-[#cbe273]/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <div class="text-center">
            <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54] text-2xl text-[#1a1c1e] shadow-lg shadow-[#ffce54]/20">
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

        <form action="{{ route('login') }}" method="POST" id="loginForm" class="mt-8 space-y-5" novalidate>
            @csrf

            <div>
                <label for="username" class="block text-sm font-semibold text-white/80">Username</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Masukkan username">
                </div>
                @error('username')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-white/80">Kata Sandi</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password" id="password" required autocomplete="current-password"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 pr-12 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Masukkan kata sandi">
                    <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-white/40 transition hover:text-white/60"
                        aria-pressed="false" aria-label="Tampilkan kata sandi">
                        <i id="toggleIcon" class="bi bi-eye"></i>
                    </button>
                </div>
                <p id="passwordClientError" class="mt-2 hidden text-xs text-rose-300">Kata sandi wajib diisi.</p>
                @error('password')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-white/70">
                    <input type="checkbox" name="remember" id="remember"
                        class="h-4 w-4 rounded border-white/20 bg-white/5 text-[#ffce54] focus:ring-[#ffce54]/30"
                        {{ old('remember') ? 'checked' : '' }}>
                    Ingat saya
                </label>
            </div>

            <button type="submit" id="btnSubmit"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                <span id="btnText">Masuk</span>
                <span id="btnSpinner" class="hidden">
                    <i class="bi bi-arrow-repeat animate-spin"></i>
                </span>
            </button>
        </form>
    </div>
</div>
@endsection
