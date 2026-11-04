{{-- Tabs across the six finance subledgers. Expects $active (route name suffix). --}}
@php
    $items = [
        'opening-balances' => 'Opening balances',
        'cash-accounts' => 'Cash accounts',
        'supplier-payments' => 'Supplier payments',
        'fixed-assets' => 'Fixed assets',
        'loans' => 'Loans',
        'equity' => 'Equity',
    ];
@endphp
<nav class="-mb-px flex flex-wrap gap-1 border-b border-slate-200" aria-label="Finance ledgers">
    @foreach ($items as $key => $label)
        <a href="{{ route('admin.finance.'.$key.'.index') }}"
           class="border-b-2 px-4 py-3 text-sm font-medium transition {{ $active === $key ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">{{ $label }}</a>
    @endforeach
</nav>
