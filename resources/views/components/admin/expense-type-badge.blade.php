@props([
    'type',
])

@php
    $label = match ((string) $type) {
        'cogs' => 'COGS',
        'operating' => 'Operating',
        default => ucfirst((string) $type),
    };

    $classes = match ((string) $type) {
        'cogs' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
        'operating' => 'bg-violet-50 text-violet-800 ring-violet-200/80',
        default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
    };
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', $classes]) }}>
    {{ $label }}
</span>
