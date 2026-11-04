<x-layouts.admin title="Sample {{ $sample->reference }}">
    @php($qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'))
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.production-samples.index') }}" class="text-sm text-teal-700">Production samples</a>
                <h2 class="flex flex-wrap items-center gap-3 text-2xl font-semibold text-slate-900">Sample {{ $sample->reference }} @include('admin.partials._status-badge', ['status' => $sample->status, 'label' => $sample->statusLabel()])</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $qty($sample->quantity) }} × {{ $sample->product?->type }} for {{ $sample->recipient_name }} · {{ $sample->sample_date->format('d M Y') }}</p>
            </div>
            @if ($sample->status === \App\Models\ProductionSample::PENDING)
                <a href="{{ route('admin.production-samples.edit', $sample) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit request</a>
            @endif
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div><dt class="text-slate-500">Product</dt><dd class="mt-1 font-medium">{{ $sample->product?->type }}</dd></div>
                <div><dt class="text-slate-500">Batch</dt><dd class="mt-1 font-medium">@if ($sample->production)<a href="{{ route('admin.productions.show', ['production' => $sample->production, 'tab' => 'traceability']) }}" class="text-teal-700 hover:underline">{{ $sample->production->batch_id }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Quantity</dt><dd class="mt-1 font-medium">{{ $qty($sample->quantity) }}</dd></div>
                <div><dt class="text-slate-500">Recipient</dt><dd class="mt-1 font-medium">{{ $sample->recipient_name }}<span class="block text-xs font-normal text-slate-500">{{ $sample->recipientTypeLabel() }}</span></dd></div>
                <div><dt class="text-slate-500">Purpose</dt><dd class="mt-1 font-medium">{{ $sample->purpose }}</dd></div>
                <div><dt class="text-slate-500">Date taken</dt><dd class="mt-1 font-medium">{{ $sample->sample_date->format('d M Y') }}</dd></div>
                <div><dt class="text-slate-500">Requested by</dt><dd class="mt-1 font-medium">{{ $sample->requested_by_name ?: ($sample->creator?->name ?? '—') }}<span class="block text-xs font-normal text-slate-500">Recorded by {{ $sample->creator?->name ?? '—' }}</span></dd></div>
                <div><dt class="text-slate-500">{{ $sample->status === 'rejected' ? 'Rejected by' : 'Approved by' }}</dt><dd class="mt-1 font-medium">{{ $sample->approver?->name ?? '—' }}@if ($sample->approved_at)<span class="block text-xs font-normal text-slate-500">{{ $sample->approved_at->format('d M Y H:i') }}</span>@endif</dd></div>
                <div><dt class="text-slate-500">Received by</dt><dd class="mt-1 font-medium">{{ $sample->received_by_name ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Cost (internal)</dt><dd class="mt-1 font-medium">@if ($sample->cost_value !== null){{ number_format((float) $sample->cost_value, 0) }} RWF <span class="text-xs font-normal text-slate-500">({{ number_format((float) $sample->unit_cost, 2) }} per unit)</span>@elseif ($sample->status === 'approved')No material cost recorded for the batch @else Set on approval @endif</dd></div>
            </dl>
            @if ($sample->notes)<p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $sample->notes }}</p>@endif
            @if ($sample->decision_notes)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><span class="font-medium">Decision notes:</span> {{ $sample->decision_notes }}</p>@endif
            <p class="mt-4 text-xs text-slate-500">Samples are not sales: no invoice is raised and no revenue is recorded. The cost appears in reports as a promotion expense.</p>
        </div>

        @if ($sample->status === \App\Models\ProductionSample::PENDING)
            @can('approve-samples')
                @if ($canApprove)
                    <form method="POST" action="{{ route('admin.production-samples.approve', $sample) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-teal-200 bg-white p-5 shadow-sm">
                        @csrf
                        <label class="flex-1 text-sm text-slate-600">Approval notes <span class="text-xs text-slate-400">(optional)</span><input name="decision_notes" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                        <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Approve — take {{ $qty($sample->quantity) }} units from stock</button>
                    </form>
                @else
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This request is above the {{ $qty($limit) }}-unit limit managers may approve. The owner must approve it.</p>
                @endif
                <form method="POST" action="{{ route('admin.production-samples.reject', $sample) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
                    @csrf
                    <label class="flex-1 text-sm text-slate-600">Reject — reason <span class="text-rose-600">*</span><input name="decision_notes" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <button type="submit" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Reject</button>
                </form>
            @else
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Waiting for a manager's approval. No stock has been taken yet.</p>
            @endcan
        @endif

        @php($canCancel = $sample->status === 'pending_approval' ? ($sample->created_by === auth()->id() || auth()->user()->can('approve-samples')) : ($sample->status === 'approved' && auth()->user()->can('approve-samples')))
        @if ($canCancel)
            <form method="POST" action="{{ route('admin.production-samples.cancel', $sample) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label class="flex-1 text-sm text-slate-600">{{ $sample->status === 'approved' ? 'Reverse this sample (the units never left) — reason' : 'Withdraw this request — reason' }}<input name="decision_notes" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $sample->status === 'approved' ? 'Reverse and restock' : 'Withdraw request' }}</button>
            </form>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            @include('admin.partials._documents', ['record' => $sample, 'routePrefix' => 'admin.production-samples', 'param' => 'productionSample', 'canRemove' => $sample->status === 'pending_approval'])
            @include('admin.partials._audit-trail', ['record' => $sample])
        </div>
    </section>
</x-layouts.admin>
