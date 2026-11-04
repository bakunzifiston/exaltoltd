<x-layouts.admin title="Review Payment">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">Reported payment</p>
                <h2 class="text-2xl font-semibold text-slate-900">{{ number_format((float) $submission->amount, 0) }} RWF · {{ $sale?->sales_id ?? 'Deleted sale' }}</h2>
                <p class="mt-1 flex items-center gap-2 text-sm text-slate-500">
                    <x-admin.payment-review-badge :status="$submission->status" />
                    Submitted {{ $submission->created_at->format('d M Y H:i') }} by {{ $submission->submitter?->name ?? 'unknown' }}
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.payment-approvals.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Back to approvals</a>
                @if ($sale && auth()->user()->can('record-sales') && (auth()->user()->can('manage-all-sales') || $sale->created_by === auth()->id()))
                    <a href="{{ route('admin.sales.show', $sale) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Open sale</a>
                @endif
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! empty($warnings))
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">⚠ Check before approving</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Proof of payment</h3>
                    <div class="mt-3">@include('admin.payment-approvals._proofs', ['proofs' => $submission->proofs, 'large' => true])</div>
                    <p class="mt-2 text-xs text-slate-400">Click a file to open it full size.</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">Payment details</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div><dt class="text-sm text-slate-500">Amount</dt><dd class="mt-1 text-sm font-semibold">{{ number_format((float) $submission->amount, 0) }} RWF</dd></div>
                        <div><dt class="text-sm text-slate-500">Method</dt><dd class="mt-1 text-sm font-medium">{{ $submission->method }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Reference</dt><dd class="mt-1 text-sm font-medium">{{ $submission->reference ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Paid on</dt><dd class="mt-1 text-sm font-medium">{{ $submission->paid_at?->format('d M Y') }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Submitted by</dt><dd class="mt-1 text-sm font-medium">{{ $submission->submitter?->name ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Received into</dt><dd class="mt-1 text-sm font-medium">{{ $submission->cashAccount?->name ?? '—' }}</dd></div>
                        @if ($submission->notes)
                            <div class="md:col-span-3"><dt class="text-sm text-slate-500">Notes</dt><dd class="mt-1 text-sm">{{ $submission->notes }}</dd></div>
                        @endif
                        @if (! $submission->isPending())
                            <div><dt class="text-sm text-slate-500">Reviewed by</dt><dd class="mt-1 text-sm font-medium">{{ $submission->reviewer?->name ?? '—' }}</dd></div>
                            <div><dt class="text-sm text-slate-500">Reviewed at</dt><dd class="mt-1 text-sm font-medium">{{ $submission->reviewed_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                            @if ($submission->review_note)
                                <div class="md:col-span-3"><dt class="text-sm text-slate-500">Review note</dt><dd class="mt-1 text-sm">{{ $submission->review_note }}</dd></div>
                            @endif
                        @endif
                    </dl>
                </div>

                @if ($submission->isPending())
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-slate-800">Decision</h3>
                        @include('admin.payment-approvals._review-actions')
                    </div>
                @endif
            </div>

            @if ($sale)
                <div class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-800">Sale</h3>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Reference</dt><dd class="font-medium">{{ $sale->sales_id }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer</dt><dd class="text-right font-medium">{{ $sale->customer_name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $sale->customer_Phone ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Channel</dt><dd class="font-medium">{{ $sale->sales_channel }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Recorded by</dt><dd class="font-medium">{{ $sale->creator?->name ?? 'Website' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Sale date</dt><dd class="font-medium">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</dd></div>
                        </dl>
                        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Invoice total</dt><dd class="font-semibold">{{ number_format((float) $sale->total_revenue, 0) }} RWF</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Confirmed paid</dt><dd class="font-medium text-emerald-700">{{ number_format((float) $sale->amount_paid, 0) }} RWF</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Awaiting approval</dt><dd class="font-medium text-amber-700">{{ number_format($sale->pending_approval_amount, 0) }} RWF</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Balance due</dt><dd class="font-medium text-rose-700">{{ number_format($sale->balance_due, 0) }} RWF</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd><x-admin.sale-status-badge :status="$sale->payment_status" type="payment" /></dd></div>
                        </dl>
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.admin>
