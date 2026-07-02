@extends('layouts.app')

@section('title', 'Anggota Farm')

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
                            <h2 class="text-lg font-semibold text-slate-900">Anggota Farm</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $farm->name }}</p>
                        </div>
                        @can('update', $farm)
                            <a href="{{ route('farm.members.create', $farm) }}"
                                class="inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-5 py-2.5 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
                                <i class="bi bi-person-plus"></i>
                                Undang Anggota
                            </a>
                        @endcan
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
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Bergabung</th>
                                    @can('update', $farm)
                                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($farm->users as $user)
                                    <tr class="transition hover:bg-slate-50/50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                            <div class="flex items-center gap-3">
                                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600">
                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                </span>
                                                {{ $user->name }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="inline-flex items-center rounded-xl px-3 py-1 text-xs font-semibold {{ $user->pivot->role === 'owner' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">
                                                {{ ucfirst($user->pivot->role) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                            {{ $user->pivot->created_at ? $user->pivot->created_at->format('d M Y') : '—' }}
                                        </td>
                                        @can('update', $farm)
                                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                                @if($user->id !== auth()->id())
                                                    <form action="{{ route('farm.members.destroy', [$farm, $user->pivot->id]) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus anggota ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                            Hapus
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-slate-400">—</span>
                                                @endif
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if($farm->users->isEmpty())
                            <div class="flex flex-col items-center py-16 text-center">
                                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                                    <i class="bi bi-people"></i>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-slate-900">Belum Ada Anggota</h3>
                                <p class="mt-1 text-sm text-slate-500">Undang anggota untuk bergabung ke farm ini.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
