@extends('layouts.app')

@section('title', 'Reminder')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-4xl">
                    <a href="{{ route('farm.show', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Reminder</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $farm->name }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('farm.reminders.calendar', $farm) }}"
                                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <i class="bi bi-calendar3"></i>
                                Kalender
                            </a>
                            <a href="{{ route('farm.reminders.create', $farm) }}"
                                class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                <i class="bi bi-bell-plus"></i>
                                Buat Reminder
                            </a>
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="mt-4 rounded-2xl border border-emerald-200/60 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mt-4 rounded-2xl border border-red-200/60 bg-red-50 px-5 py-3 text-sm font-medium text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr class="bg-slate-50/80">
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Judul</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Target</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Waktu</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($reminders as $reminder)
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $reminder->title }}</td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($reminder->targets as $target)
                                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                                        {{ $target->targetable?->name ?? '—' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                            {{ $reminder->starts_at->format('d M Y H:i') }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('farm.reminders.show', [$farm, $reminder]) }}"
                                                    class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                                    Lihat
                                                </a>
                                                @if($reminder->created_by_type === auth()->user()::class && $reminder->created_by_id === auth()->id())
                                                    <a href="{{ route('farm.reminders.edit', [$farm, $reminder]) }}"
                                                        class="rounded-xl bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('farm.reminders.destroy', [$farm, $reminder]) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus reminder ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                            Hapus
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($reminders->isEmpty())
                            <div class="flex flex-col items-center py-16 text-center">
                                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                    <i class="bi bi-bell"></i>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Reminder</h3>
                                <p class="mt-1 text-sm text-slate-500">Buat reminder untuk pengingat jadwal kebunmu.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
