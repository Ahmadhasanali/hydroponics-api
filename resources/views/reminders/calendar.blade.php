@extends('layouts.app')

@section('title', 'Kalender Reminder')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-5xl">
                    <a href="{{ route('farm.reminders.index', $farm) }}" class="inline-flex items-center gap-2 text-sm text-slate-500 transition hover:text-slate-700">
                        <i class="bi bi-arrow-left"></i>
                        Kembali
                    </a>

                    <div class="mt-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Kalender Reminder</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $start->translatedFormat('F Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('farm.reminders.calendar', [$farm, 'month' => $start->copy()->subMonth()->format('Y-m')]) }}"
                                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <a href="{{ route('farm.reminders.calendar', [$farm, 'month' => $start->copy()->addMonth()->format('Y-m')]) }}"
                                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="{{ route('farm.reminders.create', $farm) }}"
                                class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-4 py-2 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
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

                    <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
                        @php
                            $daysInMonth = $start->daysInMonth;
                            $firstDow = $start->copy()->startOfMonth()->dayOfWeek; // 0 = Minggu
                            $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                        @endphp

                        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/80">
                            @foreach($dayNames as $name)
                                <div class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $name }}</div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7">
                            @for($i = 0; $i < $firstDow; $i++)
                                <div class="min-h-24 border-b border-slate-100 bg-slate-50/40 p-2"></div>
                            @endfor

                            @for($day = 1; $day <= $daysInMonth; $day++)
                                @php
                                    $dateKey = $start->copy()->day($day)->format('Y-m-d');
                                    $dayReminders = $byDate->get($dateKey, collect());
                                @endphp
                                <div class="min-h-24 border-b border-r border-slate-100 p-2 {{ $dayReminders->isNotEmpty() ? 'bg-[#ffce54]/5' : '' }}">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl text-sm font-semibold {{ $dayReminders->isNotEmpty() ? 'bg-[#ffce54] text-[#1a1c1e]' : 'text-slate-500' }}">
                                        {{ $day }}
                                    </span>
                                    <div class="mt-1.5 space-y-1">
                                        @foreach($dayReminders->take(3) as $item)
                                            <a href="{{ route('farm.reminders.show', [$farm, $item->reminder]) }}"
                                                class="block truncate rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 transition hover:bg-[#ffce54]/30">
                                                {{ $item->reminder->title }}
                                            </a>
                                        @endforeach
                                        @if($dayReminders->count() > 3)
                                            <p class="px-1 text-[11px] font-semibold text-slate-400">+{{ $dayReminders->count() - 3 }} lainnya</p>
                                        @endif
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
