<x-layouts.admin title="View Sale">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Sale</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $sale->sales_id }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }} ·
                    @if ($sale->customer)
                        <a href="{{ route('admin.customers.show', $sale->customer) }}" class="text-teal-700 hover:underline">{{ $sale->customer_name }}</a>
                    @else
                        {{ $sale->customer_name }}
                    @endif
                    @if ($sale->sales_channel === 'Online Store')
                        · <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-sky-200">Online Store</span>
                    @else
                        · {{ $sale->sales_channel }}
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.sales.edit', $sale) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Edit</a>
                @can('delete-records')
                    <form method="POST" action="{{ route('admin.sales.destroy', $sale) }}" onsubmit="return confirm('Delete this sale?')">@csrf @method('DELETE')<button class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white">Delete</button></form>
                @endcan
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($sale->pending_approval_amount > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>{{ number_format($sale->pending_approval_amount, 0) }} RWF</strong> reported as paid is awaiting approval.
                It is counted as paid once an approver confirms it.
                @can('approve-payments')
                    <a href="#reported-payments" class="ml-1 font-medium text-amber-900 underline">Review below</a>
                @endcan
            </div>
        @endif

        {{-- Goods returned against this order (stock returns) --}}
        @if ($sale->stockReturns->isNotEmpty() || $sale->delivery_status === 'Delivered')
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                <div>
                    @if ($sale->stockReturns->isEmpty())
                        <span class="text-slate-600">No goods have been returned against this order.</span>
                    @else
                        <span class="font-medium text-slate-800">Returns:</span>
                        @foreach ($sale->stockReturns as $stockReturn)
                            <a href="{{ route('admin.stock-returns.show', $stockReturn) }}" class="text-teal-700 hover:underline">{{ $stockReturn->reference }}</a> <span class="text-slate-500">({{ strtolower($stockReturn->statusLabel()) }}, {{ number_format((float) $stockReturn->total_value, 0) }} RWF)</span>{{ $loop->last ? '' : ' · ' }}
                        @endforeach
                        @if ((float) $sale->returned_amount > 0)<span class="block text-xs text-slate-500">Credited {{ number_format((float) $sale->returned_amount, 0) }} RWF: the customer is charged {{ number_format($sale->net_total, 0) }} RWF.@if ($sale->credit_due > 0) They have paid {{ number_format($sale->credit_due, 0) }} RWF more than that.@endif</span>@endif
                    @endif
                </div>
                @if ($sale->delivery_status === 'Delivered')
                    <a href="{{ route('admin.stock-returns.create', ['customer_id' => $sale->customer_id, 'sale_id' => $sale->id]) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Record a return</a>
                @endif
            </div>
        @endif

        {{-- Payment position at a glance --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card :label="(float) $sale->returned_amount > 0 ? 'Invoice Total (after returns)' : 'Invoice Total'" :value="number_format($sale->net_total, 0) . ' RWF'" icon="banknotes" tone="teal" />
            <x-admin.kpi-card label="Amount Paid" :value="number_format($sale->amount_paid ?? 0, 0) . ' RWF'" icon="banknotes" tone="emerald" />
            <x-admin.kpi-card label="Balance Due" :value="number_format($sale->balance_due, 0) . ' RWF'" icon="clock" :tone="$sale->balance_due > 0 ? 'rose' : 'emerald'" />
            <x-admin.kpi-card
                label="Due Date"
                :value="$sale->due_date ? $sale->due_date->format('d M Y') . ($sale->is_overdue ? ' · OVERDUE' : '') : 'Not set'"
                icon="clock"
                :tone="$sale->is_overdue ? 'rose' : 'slate'"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                {{-- Sale details --}}
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Details</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-slate-500">Phone</dt><dd class="mt-1 text-sm font-medium">{{ $sale->customer_Phone ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Email</dt><dd class="mt-1 text-sm font-medium">{{ $sale->customer_email ?? $sale->barcode ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Invoice No.</dt><dd class="mt-1 text-sm font-medium">{{ $sale->invoice_number ?? '—' }}</dd></div>
                        <div class="md:col-span-2"><dt class="text-sm text-slate-500">Delivery address</dt><dd class="mt-1 text-sm font-medium">{{ $sale->delivery_address ?? '—' }}</dd></div>
                        <div>
                            <dt class="text-sm text-slate-500">Status</dt>
                            <dd class="mt-1 flex gap-2">
                                <x-admin.sale-status-badge :status="$sale->payment_status" type="payment" />
                                <x-admin.sale-status-badge :status="$sale->delivery_status" type="delivery" />
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-slate-800">Items</h3>
                        <table class="mt-2 min-w-full text-sm">
                            <thead class="bg-slate-50"><tr><th class="px-2 py-1.5 text-left">Product</th><th class="px-2 py-1.5 text-left">Batch</th><th class="px-2 py-1.5 text-right">Qty</th><th class="px-2 py-1.5 text-right">Price</th><th class="px-2 py-1.5 text-right">Total</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($sale->items as $item)
                                    <tr><td class="px-2 py-1.5">{{ $item->product?->type }}</td><td class="px-2 py-1.5">{{ $item->production?->batch_id }}</td><td class="px-2 py-1.5 text-right">{{ number_format($item->quantity_sold,2) }}</td><td class="px-2 py-1.5 text-right">{{ number_format($item->unit_price,0) }} RWF</td><td class="px-2 py-1.5 text-right">{{ number_format($item->line_total,0) }} RWF</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-200 font-semibold"><td colspan="4" class="px-2 py-2 text-right">Invoice Total</td><td class="px-2 py-2 text-right">{{ number_format($sale->total_revenue ?? 0, 0) }} RWF</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Payments ledger --}}
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">Payment History</h3>
                        <span class="text-xs text-slate-400">{{ $sale->payments->count() }} payment(s)</span>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Method</th><th class="px-4 py-2 text-left">Reference</th><th class="px-4 py-2 text-left">Proof</th><th class="px-4 py-2 text-left">Recorded By</th><th class="px-4 py-2"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($sale->payments->sortByDesc('paid_at') as $payment)
                                <tr>
                                    <td class="px-4 py-2">
                                        {{ $payment->paid_at?->format('Y-m-d') }}
                                        @if ($payment->is_backfilled)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">estimated</span>@endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium text-emerald-700">{{ number_format($payment->amount, 0) }} RWF</td>
                                    <td class="px-4 py-2">{{ $payment->method }}</td>
                                    <td class="px-4 py-2 text-slate-500">{{ $payment->reference ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($payment->submission)
                                            @include('admin.payment-approvals._proofs', ['proofs' => $payment->submission->proofs])
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-slate-500">
                                        {{ $payment->recordedBy?->name ?? 'System' }}
                                        @if ($payment->submission?->reviewer && $payment->submission->reviewed_by !== $payment->recorded_by)
                                            <span class="block text-xs text-slate-400">approved by {{ $payment->submission->reviewer->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @can('delete-records')
                                            <form method="POST" action="{{ route('admin.sales.payments.destroy', [$sale, $payment]) }}" onsubmit="return confirm('Remove this payment? The balance will be recalculated.')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No confirmed payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Reported payments (claims with proof, before and after review) --}}
                @if ($sale->paymentSubmissions->isNotEmpty())
                    <div id="reported-payments" class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-3">
                            <h3 class="text-sm font-semibold text-slate-800">Reported Payments</h3>
                            <span class="text-xs text-slate-400">{{ $sale->paymentSubmissions->where('status', \App\Models\SalePaymentSubmission::STATUS_PENDING)->count() }} awaiting approval</span>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($sale->paymentSubmissions->sortByDesc('created_at') as $submission)
                                <li class="space-y-3 px-6 py-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">
                                                {{ number_format((float) $submission->amount, 0) }} RWF · {{ $submission->method }}
                                                @if ($submission->reference)<span class="font-normal text-slate-500">· Ref {{ $submission->reference }}</span>@endif
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Paid {{ $submission->paid_at?->format('d M Y') }} · reported {{ $submission->created_at->format('d M Y H:i') }} by {{ $submission->submitter?->name ?? 'unknown' }}
                                            </p>
                                            @if ($submission->reviewed_at)
                                                <p class="mt-1 text-xs text-slate-600">
                                                    {{ $submission->statusLabel() }} by {{ $submission->reviewer?->name ?? 'unknown' }} on {{ $submission->reviewed_at->format('d M Y H:i') }}
                                                    @if ($submission->review_note)— “{{ $submission->review_note }}”@endif
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-admin.payment-review-badge :status="$submission->status" />
                                            @if ($submission->isPending() && $submission->submitted_by === auth()->id())
                                                <form method="POST" action="{{ route('admin.sales.payment-submissions.withdraw', [$sale, $submission]) }}" onsubmit="return confirm('Withdraw this payment report?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Withdraw</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    @include('admin.payment-approvals._proofs', ['proofs' => $submission->proofs])
                                    @include('admin.payment-approvals._review-actions', ['submission' => $submission])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                {{-- Record / report a payment --}}
                @php
                    $canApprove = auth()->user()->canApprovePayments();
                    $openBalance = $sale->submittable_balance;
                @endphp
                @if ($openBalance > 0)
                    <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-teal-900">{{ $canApprove ? 'Record a Payment' : 'Report a Payment' }}</h3>
                        <p class="mt-1 text-xs text-teal-800/80">
                            Open balance: <strong>{{ number_format($openBalance, 0) }} RWF</strong>
                            @if ($sale->pending_approval_amount > 0)· {{ number_format($sale->pending_approval_amount, 0) }} RWF awaiting approval @endif
                        </p>
                        @unless ($canApprove)
                            <p class="mt-1 text-xs text-teal-800/80">Attach proof of payment. It counts as paid once an approver confirms it — you'll get an email.</p>
                        @endunless
                        <form id="paymentForm" method="POST" action="{{ route('admin.sales.payments.store', $sale) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Amount (RWF)</label>
                                <input name="amount" type="number" step="0.01" min="0.01" max="{{ $openBalance }}" required value="{{ old('amount', $openBalance) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Method</label>
                                    <select name="method" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        @foreach (\App\Models\SalePayment::METHODS as $method)
                                            <option value="{{ $method }}" @selected(old('method', \App\Models\SalePayment::methodForChannel($sale->sales_channel)) === $method)>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Date paid</label>
                                    <input name="paid_at" type="date" required value="{{ old('paid_at', now()->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Reference <span class="font-normal text-slate-400">(required for MoMo / bank transfer)</span></label>
                                <input name="reference" value="{{ old('reference') }}" maxlength="191" placeholder="MoMo TX ID, bank ref, receipt no..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Proof of payment <span class="font-normal text-slate-400">(optional for cash)</span></label>
                                <input name="proofs[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-xs file:font-medium file:text-white">
                                <p class="mt-1 text-[11px] text-slate-400">Screenshot, slip or receipt · up to {{ \App\Models\SalePaymentSubmission::MAX_PROOFS }} files · JPG, PNG, WEBP or PDF · {{ \App\Models\SalePaymentSubmission::PROOF_MAX_KB / 1024 }} MB each</p>
                            </div>
                            @if ($canApprove && $cashAccounts->isNotEmpty())
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Received into</label>
                                    <select name="cash_account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">— Not specified —</option>
                                        @foreach ($cashAccounts as $account)
                                            <option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <button class="w-full rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $canApprove ? 'Record Payment' : 'Submit for Approval' }}</button>
                        </form>
                        <script>
                        (() => {
                            const form = document.getElementById('paymentForm');
                            const method = form.querySelector('[name="method"]');
                            const proofs = form.querySelector('[name="proofs[]"]');
                            const reference = form.querySelector('[name="reference"]');
                            const proofOptional = @json(\App\Models\SalePaymentSubmission::PROOF_OPTIONAL_METHODS);
                            const referenceRequired = @json(\App\Models\SalePaymentSubmission::REFERENCE_REQUIRED_METHODS);
                            const sync = () => {
                                proofs.required = !proofOptional.includes(method.value);
                                reference.required = referenceRequired.includes(method.value);
                            };
                            method.addEventListener('change', sync);
                            sync();
                        })();
                        </script>
                    </div>
                @elseif ($sale->balance_due > 0)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 shadow-sm">
                        <p class="font-semibold">Awaiting approval</p>
                        <p class="mt-1 text-xs">The whole open balance has been reported as paid. The sale is marked paid once an approver confirms it.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800 shadow-sm">
                        <p class="font-semibold">Fully paid ✓</p>
                        <p class="mt-1 text-xs">Settled {{ $sale->paid_at?->format('d M Y') ?? '' }}</p>
                    </div>
                @endif

                {{-- Status timeline --}}
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Timeline</h3>
                    <ol class="mt-4 space-y-4 border-l border-slate-200 pl-4">
                        @php
                            $timeline = collect();
                            foreach ($sale->statusEvents as $event) {
                                $timeline->push(['type' => 'status', 'at' => $event->created_at, 'event' => $event]);
                            }
                            foreach ($sale->paymentSubmissions as $submission) {
                                $timeline->push(['type' => 'reported', 'at' => $submission->created_at, 'submission' => $submission]);
                                $closedAt = $submission->reviewed_at ?? ($submission->status === \App\Models\SalePaymentSubmission::STATUS_WITHDRAWN ? $submission->updated_at : null);
                                if ($closedAt) {
                                    $timeline->push(['type' => 'reviewed', 'at' => $closedAt, 'submission' => $submission]);
                                }
                            }
                        @endphp
                        @foreach ($timeline->sortByDesc('at') as $entry)
                            @if ($entry['type'] === 'status')
                                @php
                                    $event = $entry['event'];
                                @endphp
                                <li class="relative">
                                    <span class="absolute -left-[1.30rem] top-1 h-2.5 w-2.5 rounded-full {{ $event->field === 'payment_status' ? 'bg-teal-500' : 'bg-sky-500' }}"></span>
                                    <p class="text-sm text-slate-700">
                                        <span class="font-medium">{{ $event->field === 'payment_status' ? 'Payment' : 'Delivery' }}</span>
                                        @if ($event->from_status)
                                            : {{ $event->from_status }} → <span class="font-semibold">{{ $event->to_status }}</span>
                                        @else
                                            set to <span class="font-semibold">{{ $event->to_status }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $event->created_at->format('d M Y H:i') }}
                                        @if ($event->changedBy) · by {{ $event->changedBy->name }}@endif
                                    </p>
                                </li>
                            @else
                                @php
                                    $submission = $entry['submission'];
                                    $reported = $entry['type'] === 'reported';
                                    $dot = $reported ? 'bg-amber-400' : match ($submission->status) {
                                        \App\Models\SalePaymentSubmission::STATUS_APPROVED, \App\Models\SalePaymentSubmission::STATUS_REVERSED => 'bg-emerald-500',
                                        \App\Models\SalePaymentSubmission::STATUS_REJECTED => 'bg-rose-500',
                                        default => 'bg-slate-300',
                                    };
                                    $outcome = $submission->status === \App\Models\SalePaymentSubmission::STATUS_REVERSED ? 'approved (later reversed)' : strtolower($submission->statusLabel());
                                    $by = $reported ? $submission->submitter : ($submission->reviewer ?? $submission->submitter);
                                @endphp
                                <li class="relative">
                                    <span class="absolute -left-[1.30rem] top-1 h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                                    <p class="text-sm text-slate-700">
                                        <span class="font-medium">{{ $reported ? 'Payment reported' : 'Payment '.$outcome }}</span>:
                                        {{ number_format((float) $submission->amount, 0) }} RWF ({{ $submission->method }})
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $entry['at']->format('d M Y H:i') }}
                                        @if ($by) · by {{ $by->name }}@endif
                                    </p>
                                </li>
                            @endif
                        @endforeach
                        <li class="relative">
                            <span class="absolute -left-[1.30rem] top-1 h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                            <p class="text-sm text-slate-700"><span class="font-medium">Sale created</span></p>
                            <p class="text-xs text-slate-400">{{ $sale->created_at?->format('d M Y H:i') }}</p>
                        </li>
                    </ol>
                    @if ($sale->delivered_at)
                        <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Delivered on {{ $sale->delivered_at->format('d M Y H:i') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.admin>
