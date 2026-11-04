@props([
    'amount' => 0,
])

@php
    $value = (float) $amount;

    [$label, $classes] = match (true) {
        $value <= 0 => ['No spend', 'bg-slate-100 text-slate-600 ring-slate-200/80'],
        $value > 200_000 => ['High', 'bg-amber-50 text-amber-900 ring-amber-200/80'],
        default => ['Normal', 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'],
    };
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', $classes]) }}>
    {{ $label }}
</span>
