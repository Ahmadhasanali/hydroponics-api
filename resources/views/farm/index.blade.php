@extends('layouts.app')

@section('title', 'Data Farm')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Data Farm</h2>
                        <p class="mt-1 text-sm text-slate-500">Kelola farm hidroponik Anda</p>
                    </div>
                    <a href="{{ route('farm.create') }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                        <i class="bi bi-plus-lg"></i>
                        Tambah Farm
                    </a>
                </div>

                @php
                    $user = auth()->user();
                    $farmList = $user->farms()->withCount('tanks')->get();
                @endphp

                @if($farmList->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#ffce54]/20 text-2xl text-[#d4a020]">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Farm</h3>
                        <p class="mt-1 text-sm text-slate-500">Buat farm baru untuk memulai.</p>
                        <a href="{{ route('farm.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                            <i class="bi bi-plus-lg"></i>
                            Buat Farm Baru
                        </a>
                    </div>
                @else
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($farmList as $farmItem)
                            <article class="rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:shadow-md">
                                <div class="flex items-start justify-between">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[#ffce54]/15 text-[#d4a020]">
                                        <i class="bi bi-building"></i>
                                    </div>
                                </div>
                                <h3 class="mt-4 font-semibold text-slate-900">{{ $farmItem->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $farmItem->address ?: '—' }}</p>
                                <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                                    <span><i class="bi bi-water me-1"></i>{{ $farmItem->tanks_count }} tank</span>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <a href="{{ route('farm.show', $farmItem) }}" class="rounded-2xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">Detail</a>
                                    <a href="{{ route('farm.edit', $farmItem) }}" class="rounded-2xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">Edit</a>
                                    <form action="{{ route('farm.destroy', $farmItem) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus farm {{ $farmItem->name }}? Semua data terkait akan ikut terhapus.')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-2xl bg-red-50 px-4 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100">Hapus</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
