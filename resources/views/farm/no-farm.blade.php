@extends('layouts.app')

@section('title', 'Belum Ada Farm')

@section('content')
    <div class="flex min-h-screen flex-col lg:flex-row lg:bg-slate-50">
        @include('partials.sidebar')

        <main class="flex flex-1 flex-col">
            @include('partials.topbar')

            <section class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                @include('partials.no-farm-card')
            </section>

            @include('partials.footer')
        </main>
    </div>
@endsection
