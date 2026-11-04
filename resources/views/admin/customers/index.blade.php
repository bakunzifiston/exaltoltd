<x-layouts.admin title="Customers">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Customers</h2>
                <p class="mt-1 text-sm text-slate-500">Every buyer with lifetime value, balance owed and purchase history.</p>
            </div>
            <a href="{{ route('admin.customers.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">New Customer</a>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-admin.kpi-card label="Total Customers" :value="number_format($summary['total'])" icon="users" tone="teal" />
            <x-admin.kpi-card label="New This Month" :value="number_format($summary['new_this_month'])" icon="users" tone="emerald" />
            <x-admin.kpi-card label="Outstanding Balance (AR)" :value="number_format($summary['outstanding'], 0) . ' RWF'" icon="clock" tone="rose" />
        </div>

        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex gap-3">
                <input name="search" value="{{ $search }}" placeholder="Search name, phone or email..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <input type="hidden" name="balance" value="{{ $balance }}">
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Apply</button>
                @if($search || $balance)
                    <a href="{{ route('admin.customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </div>
        </form>

        @if($balance === 'due')
            <div class="flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800">
                <span class="font-medium">Filtered:</span>
                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 ring-1 ring-rose-300">Balance Due (unsettled customers only)</span>
                <a href="{{ route('admin.customers.index') }}" class="ml-auto text-xs text-rose-600 hover:underline">Clear filter</a>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        @php
                            $sortLink = fn (string $column, string $label) =>
                                '<a class="inline-flex items-center gap-1 hover:text-teal-700" href="' .
                                route('admin.customers.index', array_merge(request()->query(), ['sort' => $column, 'direction' => ($sort === $column && $direction === 'desc') ? 'asc' : 'desc'])) .
                                '">' . $label . ($sort === $column ? ($direction === 'desc' ? ' ↓' : ' ↑') : '') . '</a>';
                        @endphp
                        <th class="px-3 py-2 text-left">{!! $sortLink('name', 'Customer') !!}</th>
                        <th class="px-3 py-2 text-left">Contact</th>
                        <th class="px-3 py-2 text-right">{!! $sortLink('sales_count', 'Orders') !!}</th>
                        <th class="px-3 py-2 text-right">{!! $sortLink('lifetime_value', 'Lifetime Value') !!}</th>
                        <th class="px-3 py-2 text-right">{!! $sortLink('outstanding', 'Balance Due') !!}</th>
                        <th class="px-3 py-2 text-left">{!! $sortLink('last_purchase_at', 'Last Purchase') !!}</th>
                        <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        @php($balance = max(0, (float) ($customer->lifetime_value ?? 0) - (float) ($customer->total_paid ?? 0)))
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-3 py-2">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-teal-800 hover:underline">{{ $customer->name }}</a>
                                @if ($customer->source === 'storefront')
                                    <span class="ml-1 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-800 ring-1 ring-sky-200">Online</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ $customer->phone ?? '—' }}
                                @if ($customer->email)<span class="block text-xs text-slate-400">{{ $customer->email }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($customer->sales_count) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($customer->lifetime_value ?? 0, 0) }} RWF</td>
                            <td class="px-3 py-2 text-right">
                                @if ($balance > 0)
                                    <span class="font-semibold text-rose-700">{{ number_format($balance, 0) }} RWF</span>
                                @else
                                    <span class="text-emerald-700">Settled</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $customer->last_purchase_at ? \Illuminate\Support\Carbon::parse($customer->last_purchase_at)->format('Y-m-d') : '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No customers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$customers" label="customers" />
    </section>
</x-layouts.admin>
