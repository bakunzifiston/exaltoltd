{{-- Shared by create and edit. Expects $products, $batches, $customers, $limit, $sample. --}}
@if ($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ $sample ? route('admin.production-samples.update', $sample) : route('admin.production-samples.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($sample) @method('PUT') @endif
    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
        <label class="text-sm text-slate-600">Product
            <select name="product_id" id="sampleProduct" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Choose…</option>
                @foreach ($products as $p)<option value="{{ $p->id }}" @selected((int) old('product_id', $sample?->product_id) === $p->id)>{{ $p->type }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm text-slate-600">Production batch
            <select name="production_id" id="sampleBatch" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" data-selected="{{ old('production_id', $sample?->production_id) }}"></select>
            <span class="mt-1 block text-xs text-slate-500">Released, in-date batches with stock, earliest best-before first.</span>
        </label>
        <label class="text-sm text-slate-600">Quantity<input type="number" name="quantity" step="0.01" min="0.01" required value="{{ old('quantity', $sample ? (float) $sample->quantity : '') }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <span class="mt-1 block text-xs text-slate-500">Above {{ rtrim(rtrim(number_format($limit, 2), '0'), '.') }} units needs the owner's approval.</span>
        </label>
        <label class="text-sm text-slate-600">Date taken<input type="date" name="sample_date" required max="{{ now()->toDateString() }}" value="{{ old('sample_date', $sample?->sample_date?->toDateString() ?? now()->toDateString()) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600">Recipient type<select name="recipient_type" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\ProductionSample::RECIPIENT_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('recipient_type', $sample?->recipient_type) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-sm text-slate-600">Customer / supermarket <span class="text-xs text-slate-400">(if on file)</span>
            <select name="customer_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">— Not a customer on file —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected((int) old('customer_id', $sample?->customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>
        </label>
        <label class="text-sm text-slate-600">Recipient name <span class="text-xs text-slate-400">(if not on file)</span><input name="recipient_name" maxlength="255" value="{{ old('recipient_name', $sample?->recipient_name) }}" placeholder="e.g. Marketing department, Kigali Food Expo" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600 md:col-span-2">Purpose<input name="purpose" required maxlength="255" list="samplePurposes" value="{{ old('purpose', $sample?->purpose) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <datalist id="samplePurposes"><option value="Tasting / in-store promotion"><option value="New listing trial"><option value="Quality check by customer"><option value="Trade fair / event"><option value="Laboratory analysis"><option value="Internal staff tasting"></datalist>
        </label>
        <label class="text-sm text-slate-600">Requested by<input name="requested_by_name" maxlength="255" value="{{ old('requested_by_name', $sample?->requested_by_name ?? auth()->user()->name) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600">Received by<input name="received_by_name" maxlength="255" value="{{ old('received_by_name', $sample?->received_by_name) }}" placeholder="Person who took delivery" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600 md:col-span-2">Notes<textarea name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $sample?->notes) }}</textarea></label>
        <label class="text-sm text-slate-600 md:col-span-3">Supporting documents <span class="text-xs text-slate-400">(request, delivery slip, photos; PDF or image, up to 5)</span><input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-2 block text-sm"></label>
    </div>
    <div class="flex flex-wrap gap-3">
        @if ($sample)
            <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save changes</button>
        @else
            <button type="submit" name="action" value="request" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Request approval</button>
            @can('approve-samples')<button type="submit" name="action" value="approve" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save and approve now</button>@endcan
        @endif
        <a href="{{ $sample ? route('admin.production-samples.show', $sample) : route('admin.production-samples.index') }}" class="px-2 py-2 text-sm text-slate-500">Cancel</a>
    </div>
</form>
<script>
(() => {
    const batches = @json($batches);
    const product = document.getElementById('sampleProduct');
    const batch = document.getElementById('sampleBatch');
    const render = () => {
        const options = batches.filter((b) => String(b.product_id) === product.value);
        batch.innerHTML = options.length
            ? options.map((b) => `<option value="${b.id}" ${String(b.id) === batch.dataset.selected ? 'selected' : ''}>${b.label}</option>`).join('')
            : '<option value="">' + (product.value ? 'No released batch with stock' : 'Choose a product first') + '</option>';
    };
    product.addEventListener('change', () => { batch.dataset.selected = ''; render(); });
    render();
})();
</script>
