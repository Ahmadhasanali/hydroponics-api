@extends('layouts.app')

@section('title', $reminder->title)

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-3xl">
                    <a href="{{ route('farm.reminders.index', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $reminder->title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $reminder->starts_at->format('d M Y H:i') }}</p>
                        </div>
                        @if($reminder->created_by_type === auth()->user()::class && $reminder->created_by_id === auth()->id())
                            <div class="flex items-center gap-2">
                                <a href="{{ route('farm.reminders.edit', [$farm, $reminder]) }}"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    <i class="bi bi-pencil"></i>
                                    Edit
                                </a>
                                <form action="{{ route('farm.reminders.destroy', [$farm, $reminder]) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus reminder ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100">
                                        <i class="bi bi-trash"></i>
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @if(session('success'))
                        <div class="mt-4 rounded-2xl border border-emerald-200/60 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="mt-6 space-y-4">
                        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Deskripsi</h3>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $reminder->body }}</p>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Target</h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($reminder->targets as $target)
                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-600">
                                        {{ $target->targetable?->name ?? '—' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200/60 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Jadwal</h3>
                            <div class="mt-3 space-y-2">
                                @foreach($reminder->occurrences->sortBy('scheduled_at') as $occurrence)
                                    <div class="flex items-center justify-between rounded-2xl border border-slate-100 px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-medium text-slate-700">
                                                {{ $occurrence->scheduled_at->format('d M Y H:i') }}
                                            </span>
                                            @if($occurrence->status->value === 'done')
                                                <span class="inline-flex items-center rounded-xl bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    <i class="bi bi-check-lg mr-1"></i>
                                                    Selesai
                                                </span>
                                            @elseif($occurrence->status->value === 'skipped')
                                                <span class="inline-flex items-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                                    Dilewati
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-xl bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                    Menunggu
                                                </span>
                                            @endif
                                        </div>
                                        @if($occurrence->status->value === 'pending')
                                            <div class="flex items-center gap-2">
                                                <form action="{{ route('farm.reminders.occurrence-done', [$farm, $occurrence]) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="rounded-xl bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                        Tandai Selesai
                                                    </button>
                                                </form>
                                                <form action="{{ route('farm.reminders.occurrence-skip', [$farm, $occurrence]) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="rounded-xl bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">
                                                        Lewati
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach

                                @if($reminder->occurrences->isEmpty())
                                    <p class="text-sm text-slate-500">Belum ada jadwal.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
