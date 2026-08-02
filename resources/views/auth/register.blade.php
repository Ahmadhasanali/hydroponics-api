@extends('layouts.auth')

@section('title', 'Buat Akun')

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
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white">Buat Akun</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">Daftar untuk mengelola sistem hidroponik Anda</p>
        </div>

        @if ($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
                <div class="flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="font-medium">Silakan periksa kembali input Anda.</span>
                </div>
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST" class="mt-8 space-y-5" novalidate>
            @csrf

            <div>
                <label for="name" class="block text-sm font-semibold text-white/80">Nama</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Masukkan nama">
                </div>
                @error('name')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-white/80">Email</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-envelope-fill"></i>
                    </span>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Masukkan email">
                </div>
                @error('email')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-white/80">Password</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password" id="password" required autocomplete="new-password"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 pr-12 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Masukkan password">
                    <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-white/40 transition hover:text-white/60"
                        aria-pressed="false" aria-label="Tampilkan password">
                        <i id="toggleIcon" class="bi bi-eye"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-white/80">Konfirmasi Password</label>
                <div class="relative mt-2">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-white/40">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                        class="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 pr-12 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                        placeholder="Ulangi password">
                    <button type="button" id="togglePasswordConfirm"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-white/40 transition hover:text-white/60"
                        aria-pressed="false" aria-label="Tampilkan konfirmasi password">
                        <i id="toggleConfirmIcon" class="bi bi-eye"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                Daftar
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-300">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] transition hover:text-[#f0b830]">Masuk</a>
        </p>
    </div>
</div>
@endsection
