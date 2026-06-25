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
                        <article class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Temperature
                                    </p>
                                    <p class="mt-3 text-4xl font-semibold text-slate-900">22°C</p>
                                </div>
                                <span
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-3xl bg-orange-100 text-orange-700">
                                    <i class="bi bi-thermometer-half text-xl"></i>
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-500">Kondisi suhu air dan lingkungan saat ini.</p>
                        </article>

                        <article
                            class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Humidity</p>
                                    <p class="mt-3 text-4xl font-semibold text-slate-900">68%</p>
                                </div>
                                <span
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-3xl bg-sky-100 text-sky-700">
                                    <i class="bi bi-droplet text-xl"></i>
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-500">Kelembapan lingkungan budidaya.</p>
                        </article>

                        <article
                            class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">pH Level</p>
                                    <p class="mt-3 text-4xl font-semibold text-slate-900">6.5</p>
                                </div>
                                <span
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-3xl bg-emerald-100 text-emerald-700">
                                    <i class="bi bi-droplet-half text-xl"></i>
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-500">Tingkat pH larutan nutrisi saat ini.</p>
                        </article>

                        <article
                            class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Water Level
                                    </p>
                                    <p class="mt-3 text-4xl font-semibold text-slate-900">75%</p>
                                </div>
                                <span
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-3xl bg-violet-100 text-violet-700">
                                    <i class="bi bi-water text-xl"></i>
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-500">Ketinggian air tank nutrisi.</p>
                        </article>
                    </div>

                    <section class="space-y-6">
                        <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Daily
                                        Monitoring</p>
                                    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Ringkasan PPM & pH</h2>
                                </div>
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700">Hari
                                    ini</span>
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
                                <div
                                    class="h-full rounded-[1.5rem] border border-dashed border-slate-300 bg-white p-6 text-center text-sm leading-6">
                                    Grafik monitoring akan muncul di sini.
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <div
                                class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-base font-semibold text-slate-900">Konsumsi Nutrisi</h3>
                                    <span
                                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Volume</span>
                                </div>
                                <p class="mt-4 text-3xl font-semibold text-slate-900">28 L</p>
                            </div>
                            <div
                                class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-base font-semibold text-slate-900">Kegiatan pH Down</h3>
                                    <span
                                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700">Aktif</span>
                                </div>
                                <p class="mt-4 text-3xl font-semibold text-slate-900">1.4 L</p>
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
