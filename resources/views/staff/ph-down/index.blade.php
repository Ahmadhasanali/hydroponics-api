@extends('layouts.staff')

@section('title', 'Catatan pH Down Saya')

@section('content')
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Catatan pH Down Saya</h2>
        <p class="mt-1 text-sm text-slate-500">Riwayat penurunan pH yang Anda catat.</p>
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-2xl border border-emerald-200/60 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($logs->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#4fc3f7]/15 text-[#4fc3f7]">
                <i class="bi bi-droplet-half"></i>
            </div>
            <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Catatan</h3>
            <a href="{{ route('staff.ph-down.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                <i class="bi bi-plus-lg"></i>
                Input Data
            </a>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200/60 bg-white shadow-sm shadow-slate-900/5">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Tank</th>
                        <th class="px-5 py-3">pH Sebelum</th>
                        <th class="px-5 py-3">pH Sesudah</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($logs as $log)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $log->log_date ? $log->log_date->format('d M Y') : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $log->tank->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $log->ph_before }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $log->ph_after }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('staff.ph-down.edit', $log) }}"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                        <i class="bi bi-pencil"></i>
                                        Edit
                                    </a>
                                    <form action="{{ route('staff.ph-down.destroy', $log) }}" method="POST" onsubmit="return confirm('Hapus catatan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                            <i class="bi bi-trash"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
@endsection
