@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
    <div class="min-h-screen lg:flex lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Activity Logs</h2>
                    <p class="mt-1 text-sm text-slate-500">Riwayat aktivitas pengguna di farm ini</p>
                </div>

                @if($logs->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Aktivitas</h3>
                        <p class="mt-1 text-sm text-slate-500">Aktivitas pengguna akan muncul di sini.</p>
                    </div>
                @else
                    <div class="mt-6 space-y-3">
                        @foreach($logs as $log)
                            <div class="flex items-start gap-4 rounded-[2rem] border border-slate-200/60 bg-white p-4 shadow-sm shadow-slate-900/5">
                                <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#ffce54]/15 text-sm font-bold text-[#d4a020]">
                                    {{ strtoupper(substr($log->user->name ?? '?', 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-700">
                                        <span class="font-semibold">{{ $log->user->name ?? 'System' }}</span>
                                        {{ $log->description }}
                                    </p>
                                    <div class="mt-1 flex items-center gap-3">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $log->action }}</span>
                                        <span class="text-xs text-slate-400">{{ $log->created_at ? $log->created_at->diffForHumans() : '—' }}</span>
                                        @if($log->entity_type)
                                            <span class="text-xs text-slate-400">{{ $log->entity_type }} #{{ $log->entity_id }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>
                @endif
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
