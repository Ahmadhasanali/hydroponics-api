@props([
    'title',
    'value',
    'badge',
    'badgeClass' => 'bg-slate-100 text-slate-700',
])

<div {{ $attributes->merge(['class' => 'rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5']) }}>
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] {{ $badgeClass }}">{{ $badge }}</span>
    </div>
    <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $value }}</p>
</div>
