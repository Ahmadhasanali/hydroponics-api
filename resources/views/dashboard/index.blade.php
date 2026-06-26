@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="min-h-screen lg:flex lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex-1">
            @include('partials.topbar')

            <section class="px-4 py-6 lg:px-8 lg:py-8">
                <div class="grid gap-6 xl:grid-cols-[repeat(2,minmax(0,1fr))]">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <x-dashboard.stat-card
                            title="Temperature"
                            value="22°C"
                            description="Kondisi suhu air dan lingkungan saat ini."
                            icon="bi bi-thermometer-half"
                            icon-bg="bg-orange-100"
                            icon-text="text-orange-700"
                        />
                        <x-dashboard.stat-card
                            title="Humidity"
                            value="68%"
                            description="Kelembapan lingkungan budidaya."
                            icon="bi bi-droplet"
                            icon-bg="bg-sky-100"
                            icon-text="text-sky-700"
                        />
                        <x-dashboard.stat-card
                            title="pH Level"
                            value="6.5"
                            description="Tingkat pH larutan nutrisi saat ini."
                            icon="bi bi-droplet-half"
                            icon-bg="bg-emerald-100"
                            icon-text="text-emerald-700"
                        />
                        <x-dashboard.stat-card
                            title="Water Level"
                            value="75%"
                            description="Ketinggian air tank nutrisi."
                            icon="bi bi-water"
                            icon-bg="bg-violet-100"
                            icon-text="text-violet-700"
                        />
                    </div>

                    <section class="space-y-6">
                        <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Daily Monitoring</p>
                                    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Ringkasan PPM & pH</h2>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700">Hari ini</span>
                            </div>
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-[1.75rem] bg-slate-50 p-5">
                                    <p class="text-sm text-slate-500">Rata-rata PPM</p>
                                    <p class="mt-3 text-3xl font-semibold text-slate-900">1,240</p>
                                </div>
                                <div class="rounded-[1.75rem] bg-slate-50 p-5">
                                    <p class="text-sm text-slate-500">Koreksi Nutrisi</p>
                                    <p class="mt-3 text-3xl font-semibold text-slate-900">2x</p>
                                </div>
                            </div>
                            <div class="mt-6 h-64 rounded-[1.75rem] bg-slate-100 p-6 text-slate-500">
                                <div class="h-full rounded-[1.5rem] border border-dashed border-slate-300 bg-white p-6 text-center text-sm leading-6">
                                    Grafik monitoring akan muncul di sini.
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <x-dashboard.metric-card
                                title="Konsumsi Nutrisi"
                                value="28 L"
                                badge="Volume"
                                badge-class="bg-emerald-100 text-emerald-700"
                            />
                            <x-dashboard.metric-card
                                title="Kegiatan pH Down"
                                value="1.4 L"
                                badge="Aktif"
                                badge-class="bg-slate-100 text-slate-700"
                            />
                        </div>
                    </section>
                </div>
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
