<x-layouts.admin title="Production samples">
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Production samples</h2>
                <p class="mt-1 text-sm text-slate-500">Free product taken from a batch for supermarkets, customers, departments or events. Samples leave stock at their production cost and never create a sale or invoice.</p>
            </div>
            <a href="{{ route('admin.production-samples.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record samples</a>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($awaiting > 0)
            <a href="{{ route('admin.production-samples.index', ['status' => 'pending_approval']) }}" class="block rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $awaiting }} sample {{ \Illuminate\Support\Str::plural('request', $awaiting) }} waiting for approval. Show them</a>
        @endif

        <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
            <label class="text-sm text-slate-600">Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, recipient, purpose, batch" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Status<select name="status" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach (\App\Models\ProductionSample::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Product<select name="product_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) ($filters['product_id'] ?? 0) === $p->id)>{{ $p->type }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Recipient type<select name="recipient_type" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All</option>@foreach (\App\Models\ProductionSample::RECIPIENT_TYPES as $value => $label)<option value="{{ $value }}" @selected(($filters['recipient_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div class="flex items-end gap-2"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button><a href="{{ route('admin.production-samples.index') }}" class="px-2 py-2 text-sm text-slate-500">Clear</a></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm" @can('delete-records') data-bulk="{{ route('admin.bulk-delete', 'production-samples') }}" data-bulk-label="samples" data-bulk-note="Approved samples have their units put back into the batch first." @endcan>
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Product · batch</th><th class="px-4 py-3">Recipient</th><th class="px-4 py-3">Purpose</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Cost</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Requested by</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($samples as $sample)
                        <tr data-bulk-id="{{ $sample->id }}">
                            <td class="px-4 py-3"><a href="{{ route('admin.production-samples.show', $sample) }}" class="font-medium text-teal-700 hover:underline">{{ $sample->reference }}</a></td>
                            <td class="px-4 py-3">{{ $sample->sample_date->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $sample->product?->type }}<span class="block text-xs text-slate-500">{{ $sample->production?->batch_id }}</span></td>
                            <td class="px-4 py-3">{{ $sample->recipient_name }}<span class="block text-xs text-slate-500">{{ $sample->recipientTypeLabel() }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ $sample->purpose }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $sample->quantity, 2), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $sample->cost_value !== null ? number_format((float) $sample->cost_value, 0).' RWF' : '—' }}</td>
                            <td class="px-4 py-3">@include('admin.partials._status-badge', ['status' => $sample->status, 'label' => $sample->statusLabel()])</td>
                            <td class="px-4 py-3 text-slate-600">{{ $sample->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No samples match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$samples" label="samples" />
    </section>
</x-layouts.admin>
