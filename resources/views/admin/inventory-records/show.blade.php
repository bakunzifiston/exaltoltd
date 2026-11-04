<x-layouts.admin title="View Inventory Record">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">Inventory Record Details</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.inventory-records.recall', $inventoryRecord) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Trace forward / recall</a>
                <a href="{{ route('admin.inventory-records.edit', $inventoryRecord) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Edit</a>
                @can('delete-records')
                    <form method="POST" action="{{ route('admin.inventory-records.destroy', $inventoryRecord) }}" onsubmit="return confirm('Delete this record?')">@csrf @method('DELETE')<button class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white">Delete</button></form>
                @endcan
            </div>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Supplier</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->supplier_name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Invoice</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->invoice_number ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Item Type</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->item_type }}</dd></div>
                <div><dt class="text-sm text-slate-500">Item Name</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->item_name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Lot / Batch No.</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->lot_number ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Supplier lot</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->supplier_lot_number ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Inspection</dt><dd class="mt-1 text-sm font-medium">{{ \App\Models\InventoryRecord::INSPECTION_STATUSES[$inventoryRecord->inspection_status] ?? '—' }}@if ($inventoryRecord->coa_reference) · CoA {{ $inventoryRecord->coa_reference }}@endif</dd></div>
                <div>
                    <dt class="text-sm text-slate-500">Expiry</dt>
                    <dd class="mt-1 text-sm font-medium">
                        @if ($inventoryRecord->expiry_date)
                            {{ $inventoryRecord->expiry_date->format('Y-m-d') }}
                            @if ($inventoryRecord->is_expired)<span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">expired</span>
                            @elseif ($inventoryRecord->is_near_expiry)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">soon</span>@endif
                        @else — @endif
                    </dd>
                </div>
                <div><dt class="text-sm text-slate-500">Recorded by</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->creator?->name ?? 'System' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Quantity in</dt><dd class="mt-1 text-sm font-medium">{{ number_format($inventoryRecord->quantity_in, 2) }} L</dd></div>
                <div><dt class="text-sm text-slate-500">Quantity out</dt><dd class="mt-1 text-sm font-medium">{{ number_format($inventoryRecord->quantity_out, 2) }} L</dd></div>
                <div><dt class="text-sm text-slate-500">On hand</dt><dd class="mt-1 text-sm font-semibold text-teal-800">{{ number_format($inventoryRecord->remainingQuantity(), 2) }} L</dd></div>
                <div><dt class="text-sm text-slate-500">Damaged</dt><dd class="mt-1 text-sm font-medium">{{ number_format($inventoryRecord->damaged, 2) }} L</dd></div>
                <div><dt class="text-sm text-slate-500">Unit Cost</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->unit_cost ? number_format($inventoryRecord->unit_cost, 0).' RWF' : '-' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Line Value</dt><dd class="mt-1 text-sm font-medium">{{ number_format($inventoryRecord->line_value, 0) }} RWF</dd></div>
                <div><dt class="text-sm text-slate-500">Payment Status</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->payment_status }}</dd></div>
                <div><dt class="text-sm text-slate-500">Overdue</dt><dd class="mt-1 text-sm font-medium">{{ $inventoryRecord->is_overdue ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </div>

        {{-- Usage history: how this specific intake (lot) has been consumed --}}
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Usage History</h3>
                    <p class="text-xs text-slate-400">Every intake, consumption and adjustment for this delivery — newest balance at the bottom.</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Received <span class="font-semibold text-slate-700">{{ number_format($inventoryRecord->quantity_in, 2) }}</span> ·
                       Consumed <span class="font-semibold text-rose-700">{{ number_format($consumed, 2) }}</span> ·
                       On hand <span class="font-semibold text-teal-800">{{ number_format($inventoryRecord->remainingQuantity(), 2) }}</span></p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Movement</th>
                            <th class="px-4 py-2 text-left">Reference</th>
                            <th class="px-4 py-2 text-right">Quantity</th>
                            <th class="px-4 py-2 text-right">Balance</th>
                            <th class="px-4 py-2 text-left">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $movement)
                            @php($isOut = (float) $movement->quantity < 0)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-2 text-slate-600">{{ $movement->moved_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-2">
                                    @php($badge = match ($movement->type) {
                                        'intake' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/80',
                                        'consumption' => 'bg-sky-50 text-sky-800 ring-sky-200/80',
                                        'reversal' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
                                        'damage' => 'bg-rose-50 text-rose-800 ring-rose-200/80',
                                        default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
                                    })
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ring-1 {{ $badge }}">{{ $movement->type }}</span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($movement->reference_type === 'production' && $movement->production)
                                        <a href="{{ route('admin.productions.show', $movement->production) }}" class="text-teal-700 hover:underline">{{ $movement->production->batch_id }}</a>
                                    @else
                                        <span class="text-slate-400">{{ $movement->notes ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-medium {{ $isOut ? 'text-rose-700' : 'text-emerald-700' }}">
                                    {{ $isOut ? '' : '+' }}{{ number_format($movement->quantity, 2) }}
                                </td>
                                <td class="px-4 py-2 text-right text-slate-700">{{ number_format($movement->running_balance, 2) }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No movements recorded for this item yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.admin>
