@props(['status'])

@php
    $classes = match ($status) {
        \App\Models\SalePaymentSubmission::STATUS_PENDING => 'bg-amber-50 text-amber-900 ring-amber-200/80',
        \App\Models\SalePaymentSubmission::STATUS_APPROVED => 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
        \App\Models\SalePaymentSubmission::STATUS_REJECTED => 'bg-rose-50 text-rose-800 ring-rose-200/80',
        default => 'bg-slate-100 text-slate-600 ring-slate-200/80',
    };
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1', $classes]) }}>
    {{ \App\Models\SalePaymentSubmission::STATUSES[$status] ?? ucfirst((string) $status) }}
</span>
