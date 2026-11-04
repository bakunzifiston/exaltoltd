<x-layouts.admin title="Stock returns">
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Stock returns</h2>
                <p class="mt-1 text-sm text-slate-500">Products sent back by supermarkets and customers against an earlier delivery. Stock and the customer's balance change only once a return is inspected and approved.</p>
            </div>
            <a href="{{ route('admin.stock-returns.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record a return</a>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($awaiting > 0)
            <a href="{{ route('admin.stock-returns.index', ['status' => 'pending_inspection']) }}" class="block rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $awaiting }} {{ \Illuminate\Support\Str::plural('return', $awaiting) }} waiting for inspection. Show them</a>
        @endif

        <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
            <label class="text-sm text-slate-600">Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, customer, order, delivery note" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Status<select name="status" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach (\App\Models\StockReturn::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Customer<select name="customer_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((int) ($filters['customer_id'] ?? 0) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Product<select name="product_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) ($filters['product_id'] ?? 0) === $p->id)>{{ $p->type }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Reason<select name="reason" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach (\App\Models\StockReturn::REASONS as $value => $label)<option value="{{ $value }}" @selected(($filters['reason'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div class="flex items-end gap-2"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button><a href="{{ route('admin.stock-returns.index') }}" class="px-2 py-2 text-sm text-slate-500">Clear</a></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Return date</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Order / delivery note</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3 text-right">Units</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Recorded by</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($returns as $return)
                        <tr>
                            <td class="px-4 py-3"><a href="{{ route('admin.stock-returns.show', $return) }}" class="font-medium text-teal-700 hover:underline">{{ $return->reference }}</a></td>
                            <td class="px-4 py-3">{{ $return->return_date->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $return->customer_name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $return->sale?->sales_id }}@if ($return->delivery_note && $return->delivery_note !== $return->sale?->sales_id)<span class="block text-xs">{{ $return->delivery_note }}</span>@endif</td>
                            <td class="px-4 py-3">{{ $return->reasonLabel() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $return->quantity_returned, 2), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $return->total_value, 0) }} RWF</td>
                            <td class="px-4 py-3">@include('admin.partials._status-badge', ['status' => $return->status, 'label' => $return->statusLabel()])</td>
                            <td class="px-4 py-3 text-slate-600">{{ $return->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No stock returns match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$returns" label="returns" />
    </section>
</x-layouts.admin>
