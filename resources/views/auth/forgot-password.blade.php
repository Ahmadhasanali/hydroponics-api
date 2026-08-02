@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <h1 class="text-center text-3xl font-semibold tracking-tight text-white">Lupa Kata Sandi</h1>
        <p class="mt-3 text-center text-sm leading-6 text-slate-300">Masukkan email Anda untuk menerima link reset password.</p>

        @if (session('status'))
            <div class="mt-8 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mt-8 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">Silakan periksa kembali input Anda.</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="mt-8 space-y-5" novalidate>
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-white/80">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                    class="mt-2 block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-400 transition focus:border-[#ffce54]/50 focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"
                    placeholder="Masukkan email">
                @error('email')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                Kirim Link Reset
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-300">
            <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] hover:text-[#f0b830]">Kembali ke login</a>
        </p>
    </div>
</div>
@endsection
