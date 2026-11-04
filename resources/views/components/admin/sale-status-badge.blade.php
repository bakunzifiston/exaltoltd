@props([
    'status',
    'type' => 'payment',
])

@php
    $label = (string) $status;

    $classes = match ($type) {
        'delivery' => match ($label) {
            'Delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
            'Pending' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
            'In Transit' => 'bg-sky-50 text-sky-800 ring-sky-200/80',
            'Returned' => 'bg-rose-50 text-rose-800 ring-rose-200/80',
            default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
        },
        default => match ($label) {
            'Paid' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
            'Partially Paid' => 'bg-orange-50 text-orange-800 ring-orange-200/80',
            'Pending' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
            'Credit' => 'bg-violet-50 text-violet-800 ring-violet-200/80',
            default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
        },
    };
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', $classes]) }}>
    {{ $label !== '' ? $label : '—' }}
</span>
