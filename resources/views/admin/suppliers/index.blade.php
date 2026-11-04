<x-layouts.admin title="Suppliers">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Suppliers</h2>
                <p class="mt-1 text-sm text-slate-500">Purchase totals, amounts owed and delivery history per supplier.</p>
            </div>
            <a href="{{ route('admin.suppliers.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">New Supplier</a>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-admin.kpi-card label="Total Suppliers" :value="number_format($summary['total'])" icon="users" tone="teal" />
            <x-admin.kpi-card label="Amount Owed (AP)" :value="number_format($summary['payable'], 0) . ' RWF'" icon="banknotes" tone="rose" />
            <x-admin.kpi-card label="Overdue Invoices" :value="number_format($summary['overdue_invoices'])" icon="clock" :tone="$summary['overdue_invoices'] > 0 ? 'rose' : 'emerald'" />
        </div>

        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex gap-3">
                <input name="search" value="{{ $search }}" placeholder="Search supplier name, phone or email..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Apply</button>
                @if($search || $sort === 'total_owed')
                    <a href="{{ route('admin.suppliers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </div>
        </form>

        @if($sort === 'total_owed')
            <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
                <span class="font-medium">Sorted by:</span>
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-300">Highest balance owed first</span>
                <a href="{{ route('admin.suppliers.index') }}" class="ml-auto text-xs text-amber-600 hover:underline">Clear filter</a>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table data-bulk="{{ route('admin.bulk-delete', 'suppliers') }}" class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Supplier</th>
                        <th class="px-3 py-2 text-left">Contact</th>
                        <th class="px-3 py-2 text-right">Deliveries</th>
                        <th class="px-3 py-2 text-right">Total Purchased</th>
                        <th class="px-3 py-2 text-right">Paid</th>
                        <th class="px-3 py-2 text-right">Balance Owed</th>
                        <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suppliers as $supplier)
                        @php($balance = max(0, (float) ($supplier->total_purchased ?? 0) - (float) ($supplier->total_paid ?? 0)))
                        <tr class="hover:bg-slate-50/60" data-bulk-id="{{ $supplier->id }}">
                            <td class="px-3 py-2"><a href="{{ route('admin.suppliers.show', $supplier) }}" class="font-medium text-teal-800 hover:underline">{{ $supplier->name }}</a></td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ $supplier->phone ?? '—' }}
                                @if ($supplier->email)<span class="block text-xs text-slate-400">{{ $supplier->email }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($supplier->inventory_records_count) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($supplier->total_purchased ?? 0, 0) }} RWF</td>
                            <td class="px-3 py-2 text-right text-slate-600">{{ number_format($supplier->total_paid ?? 0, 0) }} RWF</td>
                            <td class="px-3 py-2 text-right">
                                @if ($balance > 0)
                                    <span class="font-semibold text-rose-700">{{ number_format($balance, 0) }} RWF</span>
                                @else
                                    <span class="text-emerald-700">Settled</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.suppliers.show', $supplier) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No suppliers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$suppliers" label="suppliers" />
    </section>
</x-layouts.admin>
