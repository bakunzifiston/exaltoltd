@php($tone = match ($status) {
    'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'pending_inspection', 'pending_approval' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
    default => 'bg-slate-100 text-slate-600 ring-slate-200',
})
<span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $tone }}">{{ $label }}</span>
