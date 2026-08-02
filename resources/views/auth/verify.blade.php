@extends('layouts.auth')

@section('title', 'Verifikasi Email')

@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4 py-10">
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -left-20 top-[-6rem] h-96 w-96 rounded-full bg-[#ffce54]/10 blur-3xl"></div>
        <div class="absolute bottom-[-8rem] right-[-4rem] h-[28rem] w-[28rem] rounded-full bg-[#cbe273]/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md rounded-[1.75rem] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-10">
        <div class="text-center">
            <div class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54] text-2xl text-[#1a1c1e] shadow-lg shadow-[#ffce54]/20">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-white">Verifikasi Email</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">
                Kami telah mengirim link verifikasi ke email Anda. Klik link tersebut untuk mengaktifkan akun sebelum mengakses dashboard.
            </p>
        </div>

        @if (session('status'))
            <div class="mt-8 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('verification.send') }}" method="POST" class="mt-8">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ffce54] px-4 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm shadow-[#ffce54]/20 transition hover:bg-[#f0b830] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/30">
                <i class="bi bi-envelope-arrow-up"></i>
                Kirim ulang link verifikasi
            </button>
        </form>

        <form action="{{ route('logout') }}" method="POST" class="mt-4">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white/70 transition hover:bg-white/10">
                Keluar
            </button>
        </form>
    </div>
</div>
@endsection
