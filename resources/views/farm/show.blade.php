@extends('layouts.app')

@section('title', 'Detail Farm')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-4xl">
                    <a href="{{ route('farm.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    {{-- TODO: implement farm detail — show farm info, members, tanks --}}
                    <div class="mt-4 rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5 sm:p-8">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">{{ $farm->name ?? 'Nama Farm' }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $farm->address ?? '—' }}</p>
                            </div>
                            <a href="{{ route('farm.edit', $farm) }}" class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        </div>

                        <hr class="my-5 border-slate-200">

                        <h3 class="mb-3 text-sm font-semibold text-slate-700">Anggota Farm</h3>
                        {{-- TODO: show farm members with their roles --}}
                        <p class="text-sm text-slate-400 italic">Fitur menampilkan anggota farm belum tersedia.</p>

                        <h3 class="mb-3 mt-6 text-sm font-semibold text-slate-700">Tank</h3>
                        {{-- TODO: show tanks in this farm --}}
                        <p class="text-sm text-slate-400 italic">Daftar tank belum tersedia.</p>
                    </div>
                </div>
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
