<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Petugas Lapangan</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f8f6f2] text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                    <i class="bi bi-droplet-half"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ auth('staff')->user()->farm->name }}</p>
                    <p class="text-xs text-slate-500">{{ auth('staff')->user()->name }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-red-50 hover:text-red-600">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </button>
            </form>
        </div>
    </header>

    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4">
            @php
                $navs = [
                    ['label' => 'Dashboard', 'route' => 'staff.dashboard'],
                    ['label' => 'Monitoring', 'route' => 'staff.monitoring.create'],
                    ['label' => 'AB Mix', 'route' => 'staff.nutrient.create'],
                    ['label' => 'pH Down', 'route' => 'staff.ph-down.create'],
                    ['label' => 'Catatan Saya', 'route' => 'staff.monitoring.index'],
                    ['label' => 'Laporan', 'route' => 'staff.reports.monitoring'],
                ];
            @endphp
            @foreach($navs as $nav)
                @if(Route::has($nav['route']))
                    <a href="{{ route($nav['route']) }}"
                        class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600 transition hover:text-[#1a1c1e] {{ request()->routeIs(str_replace('.create', '.*', $nav['route']), $nav['route']) ? 'border-b-2 border-[#ffce54] text-[#1a1c1e]' : '' }}">
                        {{ $nav['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-6">
        @yield('content')
    </main>
</body>
</html>
