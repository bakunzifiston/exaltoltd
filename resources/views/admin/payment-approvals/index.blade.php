<x-layouts.admin title="Payment Approvals">
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Payment Approvals</h2>
                <p class="mt-1 text-sm text-slate-500">Check each reported payment against the receiving account before approving. A sale is only marked paid once its payment is approved.</p>
            </div>
            @if (($counts[\App\Models\SalePaymentSubmission::STATUS_PENDING] ?? 0) > 0)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    <strong>{{ $counts[\App\Models\SalePaymentSubmission::STATUS_PENDING] }}</strong> awaiting approval ·
                    <strong>{{ number_format($pendingTotal, 0) }} RWF</strong>
                </div>
            @endif
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
            @foreach (\App\Models\SalePaymentSubmission::STATUSES as $key => $label)
                <a href="{{ route('admin.payment-approvals.index', ['status' => $key]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold ring-1 {{ $status === $key ? 'bg-teal-700 text-white ring-teal-700' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' }}">
                    {{ $label }} <span class="ml-1 opacity-75">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Submitted</th>
                        <th class="px-3 py-2 text-left">Sale</th>
                        <th class="px-3 py-2 text-left">Customer</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2 text-left">Method / Ref</th>
                        <th class="px-3 py-2 text-left">Submitted by</th>
                        <th class="px-3 py-2 text-left">Proof</th>
                        @if ($status !== \App\Models\SalePaymentSubmission::STATUS_PENDING)
                            <th class="px-3 py-2 text-left">Reviewed</th>
                        @endif
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($submissions as $submission)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ $submission->created_at->format('d M Y H:i') }}
                                <span class="block text-xs text-slate-400">{{ $submission->created_at->diffForHumans() }}</span>
                            </td>
                            <td class="px-3 py-2">
                                {{ $submission->sale?->sales_id ?? '—' }}
                                @if ($submission->sale?->sales_channel === 'Online Store')
                                    <span class="ml-1 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-800 ring-1 ring-sky-200">Online</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $submission->sale?->customer_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $submission->amount, 0) }} RWF</td>
                            <td class="px-3 py-2">{{ $submission->method }}<span class="block text-xs text-slate-400">{{ $submission->reference ?? '—' }}</span></td>
                            <td class="px-3 py-2">{{ $submission->submitter?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $submission->proofs_count }} file(s)</td>
                            @if ($status !== \App\Models\SalePaymentSubmission::STATUS_PENDING)
                                <td class="px-3 py-2 text-xs text-slate-500">
                                    {{ $submission->reviewer?->name ?? '—' }}
                                    @if ($submission->reviewed_at)<span class="block">{{ $submission->reviewed_at->format('d M Y H:i') }}</span>@endif
                                </td>
                            @endif
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.payment-approvals.show', $submission) }}" class="rounded-lg {{ $submission->isPending() ? 'bg-teal-700 text-white hover:bg-teal-800' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }} px-3 py-1.5 text-xs font-medium">
                                    {{ $submission->isPending() ? 'Review' : 'View' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">
                            {{ $status === \App\Models\SalePaymentSubmission::STATUS_PENDING ? 'Nothing waiting — all reported payments have been reviewed.' : 'No payments in this list.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$submissions" label="payments" />
    </section>
</x-layouts.admin>
