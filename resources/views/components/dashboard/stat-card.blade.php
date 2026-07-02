@props([
    'title',
    'value',
    'description',
    'icon',
    'iconBg' => 'bg-slate-100',
    'iconText' => 'text-slate-700',
])

<article {{ $attributes->merge(['class' => 'rounded-[2rem] border border-slate-200/60 bg-white p-5 shadow-sm shadow-slate-900/5']) }}>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">{{ $title }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-900">{{ $value }}</p>
        </div>
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-3xl {{ $iconBg }} {{ $iconText }}">
            <i class="{{ $icon }} text-xl"></i>
        </span>
    </div>
    <p class="mt-4 text-sm leading-6 text-slate-500">{{ $description }}</p>
</article>
