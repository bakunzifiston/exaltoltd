<x-layouts.admin title="Recall trace">
    @php($qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'))
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.inventory-records.show', $lot) }}" class="text-sm text-teal-700">Delivery #{{ $lot->id }}</a>
                <h2 class="text-2xl font-semibold text-slate-900">Trace forward: {{ $lot->item_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">From {{ $lot->supplier?->name ?? $lot->supplier_name }} · received {{ $lot->record_date?->format('d M Y') }}@if ($lot->supplier_lot_number) · supplier lot {{ $lot->supplier_lot_number }}@endif @if ($lot->lot_number) · our lot {{ $lot->lot_number }}@endif</p>
            </div>
            <a href="{{ route('admin.inventory-records.recall.export', $lot) }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Download recall list (Excel)</a>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Batches made with it</p><p class="mt-1 text-xl font-semibold">{{ $recall['batches']->count() }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Units shipped from those batches</p><p class="mt-1 text-xl font-semibold">{{ $qty($recall['shipments']->sum('quantity')) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Customers to contact</p><p class="mt-1 text-xl font-semibold">{{ $recall['customers']->count() }}</p></div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">Affected batches</h3>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Batch</th><th class="px-4 py-2">Product</th><th class="px-4 py-2">Made</th><th class="px-4 py-2">Best before</th><th class="px-4 py-2 text-right">Lot qty used</th><th class="px-4 py-2 text-right">Sold</th><th class="px-4 py-2 text-right">Still in stock</th><th class="px-4 py-2">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recall['batches'] as $row)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('admin.productions.show', ['production' => $row['batch'], 'tab' => 'traceability']) }}" class="font-medium text-teal-700 hover:underline">{{ $row['batch']->batch_id }}</a></td>
                            <td class="px-4 py-2">{{ $row['batch']->product?->type }}</td>
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($row['batch']->production_date)->format('d M Y') }}</td>
                            <td class="px-4 py-2">{{ $row['batch']->best_before_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($row['quantity_used']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($row['summary']['sold']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($row['summary']['remaining']) }}</td>
                            <td class="px-4 py-2">{{ \App\Models\Production::QC_STATUSES[$row['batch']->qc_status] ?? $row['batch']->qc_status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No production batch has used this delivery.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">Customers who received affected product</h3>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Customer</th><th class="px-4 py-2">Phone</th><th class="px-4 py-2">Address</th><th class="px-4 py-2">Batches</th><th class="px-4 py-2 text-right">Units</th><th class="px-4 py-2">Last sale</th><th class="px-4 py-2">Delivered</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recall['customers'] as $c)
                        <tr>
                            <td class="px-4 py-2 font-medium text-slate-900">@if ($c['customer_id'])<a href="{{ route('admin.customers.show', $c['customer_id']) }}" class="hover:underline">{{ $c['customer'] }}</a>@else{{ $c['customer'] }}@endif</td>
                            <td class="px-4 py-2">{{ $c['phone'] ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $c['address'] ?: '—' }}</td>
                            <td class="px-4 py-2">{{ implode(', ', $c['batches']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($c['quantity']) }}</td>
                            <td class="px-4 py-2">{{ $c['last_sale'] ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $c['delivered'] ? 'Yes' : 'Not yet' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No customer has received product made with this delivery.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-800">Incidents on this delivery</h3>
            @foreach ($incidents as $incident)
                <div class="rounded-lg border border-slate-200 p-3 text-sm"><p class="text-xs text-slate-500">{{ $incident->typeLabel() }} · {{ $incident->opened_at->format('d M Y') }} · {{ ucfirst($incident->status) }}</p><p class="font-medium">{{ $incident->title }}</p><p class="text-slate-600">{{ $incident->description }}</p></div>
            @endforeach
            <form method="POST" action="{{ route('admin.inventory-records.incidents.store', $lot) }}" class="grid gap-3 md:grid-cols-3">
                @csrf
                <label class="text-sm text-slate-600">Type<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\TraceIncident::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm text-slate-600">Opened on<input type="date" name="opened_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-sm text-slate-600">Title<input name="title" required maxlength="150" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-sm text-slate-600 md:col-span-3">What happened<textarea name="description" required rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
                <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record incident</button></div>
            </form>
        </div>
    </section>
</x-layouts.admin>
