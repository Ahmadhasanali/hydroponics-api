@extends('layouts.auth')

@section('title', 'Login Petugas Lapangan')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                    <i class="bi bi-droplet-half text-xl"></i>
                </div>
                <h1 class="mt-4 text-2xl font-bold">Login Petugas Lapangan</h1>
                <p class="mt-1 text-sm text-white/60">Masuk untuk mencatat data di kebun yang ditugaskan.</p>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 backdrop-blur">
                @if($errors->any())
                    <div class="mb-4 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('staff.login.attempt') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="farm_name" class="block text-sm font-semibold text-white/80">Nama Kebun</label>
                        <input type="text" name="farm_name" id="farm_name" value="{{ old('farm_name') }}" required autofocus
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-semibold text-white/80">Username</label>
                        <input type="text" name="username" id="username" value="{{ old('username') }}" required
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-white/80">Password</label>
                        <input type="password" name="password" id="password" required
                            class="mt-1.5 block w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20">
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        Masuk
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-white/50">
                    Bukan petugas? <a href="{{ route('login') }}" class="font-semibold text-[#ffce54] hover:underline">Login User</a>
                </p>
            </div>
        </div>
    </div>
@endsection
