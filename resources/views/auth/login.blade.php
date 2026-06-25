@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="login-card">
    <!-- Logo & Header -->
    <div class="text-center">
        <div class="brand-logo">
            <i class="bi bi-droplet-half"></i>
        </div>
        <h1 class="login-title">Selamat Datang</h1>
        <p class="login-subtitle">Masuk untuk mengelola sistem hidroponik Anda</p>
    </div>

    <!-- Session Alert Messages -->
    @if ($errors->any())
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span class="small fw-semibold">Silakan periksa kembali input Anda.</span>
            </div>
        </div>
    @endif

    <!-- Form -->
    <form action="{{ url('/login') }}" method="POST" id="loginForm" novalidate>
        @csrf

        <!-- Username Input -->
        <div class="mb-4">
            <label for="username" class="form-label">Username</label>
            <div class="input-group-custom d-flex align-items-center @error('username') has-error @enderror">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input 
                    type="text" 
                    class="form-control" 
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
                <div class="validation-error-msg">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <!-- Password Input -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="password" class="form-label mb-0">Kata Sandi</label>
            </div>
            <div class="input-group-custom d-flex align-items-center @error('password') has-error @enderror">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••" 
                    autocomplete="current-password" 
                    required 
                    enterkeyhint="done"
                >
                <button 
                    type="button" 
                    class="btn-toggle-password" 
                    id="togglePassword" 
                    aria-pressed="false" 
                    aria-label="Tampilkan kata sandi"
                >
                    <i class="bi bi-eye" id="toggleIcon"></i>
                </button>
            </div>
            <div class="validation-error-msg d-none" id="passwordClientError" aria-live="polite">
                <i class="bi bi-info-circle-fill"></i>
                <span>Kata sandi wajib diisi.</span>
            </div>
            @error('password')
                <div class="validation-error-msg">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check d-flex align-items-center gap-2">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">
                    Ingat Saya
                </label>
            </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-submit" id="btnSubmit">
            Masuk Sekarang
        </button>
    </form>
</div>
@endsection
