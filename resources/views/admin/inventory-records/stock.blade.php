<x-layouts.admin title="Stock on Hand">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Stock on Hand</h2>
                <p class="mt-1 text-sm text-slate-500">Current inventory grouped by item, broken down into the open deliveries (lots) it came from — FIFO order.</p>
            </div>
            <a href="{{ route('admin.inventory-records.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">All records</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card label="Items in stock" :value="number_format($summary['items'])" icon="box" tone="teal" />
            <x-admin.kpi-card label="Stock value (FIFO cost)" :value="number_format($summary['total_value'], 0) . ' RWF'" icon="banknotes" tone="gold" />
            <x-admin.kpi-card label="Low-stock items" :value="number_format($summary['low_stock'])" icon="clock" :tone="$summary['low_stock'] > 0 ? 'rose' : 'emerald'" />
            <x-admin.kpi-card label="Expiry alerts" :value="number_format($summary['expiry_alerts'])" icon="clock" :tone="$summary['expiry_alerts'] > 0 ? 'rose' : 'emerald'" />
        </div>

        @forelse ($groups as $group)
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-800">{{ $group['name'] }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $group['item_type'] }}</span>
                        @if ($group['low_stock'])
                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">Low stock</span>
                        @endif
                    </div>
                    <div class="text-right text-xs text-slate-500">
                        On hand <span class="font-semibold text-teal-800">{{ number_format($group['total_remaining'], 2) }}</span>
                        @if ($group['reorder_level'] > 0) · reorder at {{ number_format($group['reorder_level'], 2) }}@endif
                        · value <span class="font-semibold text-slate-700">{{ number_format($group['total_value'], 0) }} RWF</span>
                    </div>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-2 text-left">Received</th>
                            <th class="px-5 py-2 text-left">Lot / Invoice</th>
                            <th class="px-5 py-2 text-left">Supplier</th>
                            <th class="px-5 py-2 text-right">On hand</th>
                            <th class="px-5 py-2 text-right">Unit cost</th>
                            <th class="px-5 py-2 text-right">Value</th>
                            <th class="px-5 py-2 text-left">Expiry</th>
                            <th class="px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($group['lots'] as $lot)
                            <tr class="hover:bg-slate-50/60 {{ $lot->is_expired ? 'bg-rose-50/40' : '' }}">
                                <td class="px-5 py-2 text-slate-600">{{ $lot->record_date?->format('Y-m-d') }}</td>
                                <td class="px-5 py-2">{{ $lot->lot_number ?? ($lot->invoice_number ? 'Inv. ' . $lot->invoice_number : '—') }}</td>
                                <td class="px-5 py-2 text-slate-600">{{ $lot->supplier_name }}</td>
                                <td class="px-5 py-2 text-right font-medium">{{ number_format($lot->remainingQuantity(), 2) }}</td>
                                <td class="px-5 py-2 text-right text-slate-600">{{ $lot->unit_cost ? number_format($lot->unit_cost, 0) . ' RWF' : '—' }}</td>
                                <td class="px-5 py-2 text-right">{{ number_format($lot->line_value, 0) }} RWF</td>
                                <td class="px-5 py-2">
                                    @if ($lot->expiry_date)
                                        {{ $lot->expiry_date->format('Y-m-d') }}
                                        @if ($lot->is_expired)<span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">expired</span>
                                        @elseif ($lot->is_near_expiry)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">soon</span>@endif
                                    @else <span class="text-slate-400">—</span> @endif
                                </td>
                                <td class="px-5 py-2 text-right"><a href="{{ route('admin.inventory-records.show', $lot) }}" class="text-teal-700 hover:underline">History</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500 shadow-sm">No stock on hand.</div>
        @endforelse

        <x-admin.table-pagination :paginator="$groups" label="items" />
    </section>
</x-layouts.admin>
