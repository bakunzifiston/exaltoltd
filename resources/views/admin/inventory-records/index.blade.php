<x-layouts.admin title="Inventory Records">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Inventory Records</h2>
                <p class="mt-1 text-sm text-slate-500">Track supplier inventory and payment status.</p>
                <p class="mt-1 text-xs text-slate-400"><span class="font-medium text-slate-500">In</span> = received · <span class="font-medium text-slate-500">Out</span> = used (production updates this) · <span class="font-medium text-slate-500">On hand</span> = In − Out</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.inventory-records.stock') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Stock on Hand</a>
                <a href="{{ route('admin.inventory-records.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">New Record</a>
            </div>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="search" value="{{ $search }}" placeholder="Search supplier/item/invoice..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="payment_status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All payment statuses</option>
                    <option value="Paid" @selected(request('payment_status')==='Paid')>Paid</option>
                    <option value="Partial" @selected(request('payment_status')==='Partial')>Partially Paid</option>
                    <option value="Unpaid" @selected(request('payment_status')==='Unpaid')>Unpaid</option>
                </select>
                <select name="item_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All item types</option>
                    <option value="Product" @selected(request('item_type')==='Product')>Product</option>
                    <option value="Raw Material" @selected(request('item_type')==='Raw Material')>Raw Material</option>
                </select>
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table data-bulk="{{ route('admin.bulk-delete', 'inventory-records') }}" class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Supplier</th><th class="px-3 py-2 text-left">Invoice</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-right">In</th><th class="px-3 py-2 text-right">Out</th><th class="px-3 py-2 text-right">On hand</th><th class="px-3 py-2 text-left">Unit Cost</th><th class="px-3 py-2 text-left">Line Value</th><th class="px-3 py-2 text-left">Total</th><th class="px-3 py-2 text-left">Paid</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Overdue</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($records as $record)
                    <tr>
                        <td class="px-3 py-2">{{ $record->supplier_name }}</td>
                        <td class="px-3 py-2">{{ $record->invoice_number }}</td>
                        <td class="px-3 py-2">{{ $record->item_type }}</td>
                        <td class="px-3 py-2">{{ $record->item_name }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($record->quantity_in, 2) }} L</td>
                        <td class="px-3 py-2 text-right">{{ number_format($record->quantity_out, 2) }} L</td>
                        <td class="px-3 py-2 text-right font-medium text-slate-900">{{ number_format($record->remainingQuantity(), 2) }} L</td>
                        <td class="px-3 py-2">{{ $record->unit_cost ? number_format($record->unit_cost, 0).' RWF' : '-' }}</td>
                        <td class="px-3 py-2">{{ number_format($record->line_value, 0) }} RWF</td>
                        <td class="px-3 py-2">{{ $record->total_amount ? number_format($record->total_amount, 0).' RWF' : '-' }}</td>
                        <td class="px-3 py-2">{{ number_format($record->amount_paid ?? 0, 0) }} RWF</td>
                        <td class="px-3 py-2">{{ $record->payment_status }}</td>
                        <td class="px-3 py-2">{{ $record->is_overdue ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ $record->record_date?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.inventory-records.show', $record) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                <a href="{{ route('admin.inventory-records.edit', $record) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('admin.inventory-records.destroy', $record) }}" onsubmit="return confirm('Delete this inventory record?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="px-4 py-6 text-center text-slate-500">No records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$records" label="records" />
    </section>
</x-layouts.admin>
