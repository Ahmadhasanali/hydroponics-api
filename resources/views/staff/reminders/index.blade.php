@extends('layouts.staff')

@section('title', 'Reminder')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Reminder</h2>
            <p class="mt-1 text-sm text-slate-500">Daftar reminder yang Anda buat atau menjadi target.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('staff.reminders.calendar') }}"
                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <i class="bi bi-calendar3"></i>
                Kalender
            </a>
            <a href="{{ route('staff.reminders.create') }}"
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

    @if($reminders->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                <i class="bi bi-bell"></i>
            </div>
            <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Reminder</h3>
            <p class="mt-1 text-sm text-slate-500">Buat reminder untuk pengingat jadwal pekerjaanmu.</p>
            <a href="{{ route('staff.reminders.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                <i class="bi bi-plus-lg"></i>
                Buat Reminder
            </a>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Judul</th>
                        <th class="px-5 py-3">Target</th>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($reminders as $reminder)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-900">{{ $reminder->title }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($reminder->targets as $target)
                                        <span class="inline-flex items-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ $target->targetable?->name ?? '—' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $reminder->starts_at->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                @if($reminder->occurrences()->where('status', 'done')->exists())
                                    <span class="inline-flex items-center rounded-xl bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">Selesai</span>
                                @elseif($reminder->occurrences()->where('status', 'skipped')->exists())
                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Dilewati</span>
                                @else
                                    <span class="inline-flex items-center rounded-xl bg-[#ffce54]/20 px-2.5 py-1 text-xs font-semibold text-[#8a6d00]">Menunggu</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3">
                                @if($reminder->created_by_type === auth('staff')->user()::class && $reminder->created_by_id === auth('staff')->id())
                                    <form action="{{ route('staff.reminders.destroy', $reminder) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus reminder ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
