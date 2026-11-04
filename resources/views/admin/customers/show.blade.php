<x-layouts.admin title="Customer Profile">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Customer</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $customer->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $customer->phone ?? 'No phone' }} · {{ $customer->email ?? 'No email' }}
                    @if ($customer->source === 'storefront') · <span class="text-sky-700">Online store customer</span>@endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Edit</a>
                <a href="{{ route('admin.customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Back</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card label="Lifetime Value" :value="number_format($stats['lifetime_value'], 0) . ' RWF'" icon="banknotes" tone="teal" />
            <x-admin.kpi-card label="Orders" :value="number_format($stats['orders'])" icon="box" tone="slate" />
            <x-admin.kpi-card label="Avg Order Value" :value="number_format($stats['avg_order_value'], 0) . ' RWF'" icon="chart" tone="gold" />
            <x-admin.kpi-card label="Balance Due" :value="number_format($stats['outstanding'], 0) . ' RWF'" icon="clock" :tone="$stats['outstanding'] > 0 ? 'rose' : 'emerald'" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">Purchase History</h3>
                        <p class="text-xs text-slate-400">
                            {{ $stats['first_purchase'] ? 'Customer since ' . \Illuminate\Support\Carbon::parse($stats['first_purchase'])->format('M Y') : 'No purchases yet' }}
                        </p>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Sale</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-right">Items</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Balance</th>
                                <th class="px-3 py-2 text-left">Payment</th>
                                <th class="px-3 py-2 text-left">Delivery</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($sales as $sale)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2"><a href="{{ route('admin.sales.show', $sale) }}" class="font-medium text-teal-800 hover:underline">{{ $sale->sales_id }}</a></td>
                                    <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2 text-right">{{ $sale->items_count }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($sale->total_revenue ?? 0, 0) }} RWF</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($sale->balance_due > 0)
                                            <span class="font-medium text-rose-700">{{ number_format($sale->balance_due, 0) }} RWF</span>
                                        @else
                                            <span class="text-emerald-700">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2"><x-admin.sale-status-badge :status="$sale->payment_status" type="payment" /></td>
                                    <td class="px-3 py-2"><x-admin.sale-status-badge :status="$sale->delivery_status" type="delivery" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No purchases recorded for this customer.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-admin.table-pagination :paginator="$sales" label="customers" />
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Details</h3>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-slate-500">Address</dt><dd class="mt-0.5 font-medium">{{ $customer->address ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">District / Province</dt><dd class="mt-0.5 font-medium">{{ $customer->district ?? '—' }} {{ $customer->province ? '· ' . $customer->province : '' }}</dd></div>
                        <div><dt class="text-slate-500">Last purchase</dt><dd class="mt-0.5 font-medium">{{ $stats['last_purchase'] ? \Illuminate\Support\Carbon::parse($stats['last_purchase'])->format('Y-m-d') : '—' }}</dd></div>
                        @if ($customer->notes)
                            <div><dt class="text-slate-500">Notes</dt><dd class="mt-0.5 whitespace-pre-line text-slate-700">{{ $customer->notes }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Recent Payments</h3>
                    <ul class="mt-3 space-y-3">
                        @forelse($recentPayments as $payment)
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <div>
                                    <p class="font-medium text-slate-800">{{ number_format($payment->amount, 0) }} RWF <span class="font-normal text-slate-500">via {{ $payment->method }}</span></p>
                                    <p class="text-xs text-slate-400">
                                        {{ $payment->paid_at?->format('Y-m-d') }} ·
                                        <a href="{{ route('admin.sales.show', $payment->sale_id) }}" class="text-teal-700 hover:underline">{{ $payment->sale?->sales_id }}</a>
                                    </p>
                                </div>
                                @if ($payment->is_backfilled)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">estimated</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">No payments recorded yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </section>
</x-layouts.admin>
