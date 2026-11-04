<x-layouts.admin title="Supplier Profile">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Supplier</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $supplier->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $supplier->phone ?? 'No phone' }} · {{ $supplier->email ?? 'No email' }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Edit</a>
                <a href="{{ route('admin.suppliers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Back</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card label="Total Purchased" :value="number_format($stats['total_purchased'], 0) . ' RWF'" icon="banknotes" tone="teal" />
            <x-admin.kpi-card label="Balance Owed" :value="number_format($stats['balance'], 0) . ' RWF'" icon="clock" :tone="$stats['balance'] > 0 ? 'rose' : 'emerald'" />
            <x-admin.kpi-card label="Deliveries" :value="number_format($stats['deliveries'])" icon="box" tone="slate" />
            <x-admin.kpi-card label="Overdue Invoices" :value="number_format($stats['overdue_invoices'])" icon="clock" :tone="$stats['overdue_invoices'] > 0 ? 'rose' : 'emerald'" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">Delivery & Invoice History</h3>
                        <p class="text-xs text-slate-400">{{ $stats['last_delivery'] ? 'Last delivery ' . \Illuminate\Support\Carbon::parse($stats['last_delivery'])->format('Y-m-d') : 'No deliveries yet' }}</p>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-right">Qty In</th>
                                <th class="px-3 py-2 text-right">Invoice Total</th>
                                <th class="px-3 py-2 text-right">Balance</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Due</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($records as $record)
                                <tr class="hover:bg-slate-50/60 {{ $record->is_overdue ? 'bg-rose-50/40' : '' }}">
                                    <td class="px-3 py-2">{{ $record->record_date?->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('admin.inventory-records.show', $record) }}" class="font-medium text-teal-800 hover:underline">{{ $record->item_name }}</a>
                                        @if ($record->invoice_number)<span class="block text-xs text-slate-400">Inv. {{ $record->invoice_number }}</span>@endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ number_format($record->quantity_in, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($record->total_amount ?? 0, 0) }} RWF</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($record->remaining_balance > 0)
                                            <span class="font-medium text-rose-700">{{ number_format($record->remaining_balance, 0) }} RWF</span>
                                        @else
                                            <span class="text-emerald-700">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @php($statusClasses = match ($record->payment_status) {
                                            'Paid' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
                                            'Partial' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
                                            default => 'bg-rose-50 text-rose-800 ring-rose-200/80',
                                        })
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses }}">{{ $record->payment_status ?? 'Unpaid' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $record->payment_due_date?->format('Y-m-d') ?? '—' }}
                                        @if ($record->is_overdue)<span class="ml-1 text-xs font-semibold text-rose-700">overdue</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No inventory deliveries recorded for this supplier.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-admin.table-pagination :paginator="$records" label="suppliers" />
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Details</h3>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-slate-500">Address</dt><dd class="mt-0.5 font-medium">{{ $supplier->address ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Total paid</dt><dd class="mt-0.5 font-medium">{{ number_format($stats['total_paid'], 0) }} RWF</dd></div>
                        <div><dt class="text-slate-500">Damaged units received</dt><dd class="mt-0.5 font-medium {{ $stats['damaged_units'] > 0 ? 'text-rose-700' : '' }}">{{ number_format($stats['damaged_units'], 2) }}</dd></div>
                        @if ($supplier->notes)
                            <div><dt class="text-slate-500">Notes</dt><dd class="mt-0.5 whitespace-pre-line text-slate-700">{{ $supplier->notes }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </section>
</x-layouts.admin>
