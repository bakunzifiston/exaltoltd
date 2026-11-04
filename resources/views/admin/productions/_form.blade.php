@csrf

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div><label class="mb-1 block text-sm">Batch ID</label><input name="batch_id" value="{{ old('batch_id', $production->batch_id ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Product</label>
        <select id="product_id" name="product_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Select product</option>
            @foreach($products as $id => $label)
                <option value="{{ $id }}" @selected((string) old('product_id', $production->product_id ?? '') === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm">Barcode</label>
        <input
            id="barcode"
            name="barcode"
            type="text"
            readonly
            required
            value="{{ old('barcode', $production->barcode ?? '') }}"
            placeholder="Select a product first"
            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
        >
        <p class="mt-1 text-xs text-slate-500">Filled automatically from the selected product.</p>
    </div>
    <div><label class="mb-1 block text-sm">Quantity Produced</label><input name="quantity_produced" type="number" step="0.01" required value="{{ old('quantity_produced', $production->quantity_produced ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Damaged</label><input name="damaged" type="number" step="0.01" required value="{{ old('damaged', $production->damaged ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Production Date</label><input name="production_date" type="date" required value="{{ old('production_date', isset($production) && $production->production_date ? \Illuminate\Support\Carbon::parse($production->production_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Responsible Worker</label>
        @php($selectedEmployee = (int) old('employee_id', $production->employee_id ?? 0))
        <select name="employee_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Select employee</option>
            @foreach ($employees ?? [] as $employee)
                <option value="{{ $employee->id }}" @selected($selectedEmployee === $employee->id)>
                    {{ $employee->name }}@if ($employee->position) — {{ $employee->position }}@endif
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">The worker who made this batch — drives the Team Performance output report (no login required).</p>
    </div>
    @php($dateValue = fn ($field) => old($field, isset($production) && $production->{$field} ? $production->{$field}->format('Y-m-d') : ''))
    @php($timeValue = fn ($field) => old($field, isset($production) && $production->{$field} ? $production->{$field}->format('Y-m-d\TH:i') : ''))
    <div><label class="mb-1 block text-sm">Packaging Date <span class="text-xs text-slate-400">(optional)</span></label><input name="packaging_date" type="date" value="{{ $dateValue('packaging_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Best Before <span class="text-xs text-slate-400">(optional)</span></label>
        <input id="best_before_date" name="best_before_date" type="date" value="{{ $dateValue('best_before_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p id="shelfLifeHint" class="mt-1 text-xs text-slate-500"></p>
    </div>
    <div><label class="mb-1 block text-sm">Use By / Expiry <span class="text-xs text-slate-400">(optional)</span></label><input name="expiry_date" type="date" value="{{ $dateValue('expiry_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Started <span class="text-xs text-slate-400">(optional)</span></label><input name="started_at" type="datetime-local" value="{{ $timeValue('started_at') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Finished <span class="text-xs text-slate-400">(optional)</span></label><input name="finished_at" type="datetime-local" value="{{ $timeValue('finished_at') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Shift <span class="text-xs text-slate-400">(optional)</span></label>
        <input name="shift" list="shiftOptions" maxlength="50" value="{{ old('shift', $production->shift ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <datalist id="shiftOptions"><option value="Morning"><option value="Afternoon"><option value="Night"></datalist>
    </div>
    <div><label class="mb-1 block text-sm">Production Line / Equipment <span class="text-xs text-slate-400">(optional)</span></label><input name="production_line" maxlength="100" value="{{ old('production_line', $production->production_line ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Recipe Version <span class="text-xs text-slate-400">(optional)</span></label><input name="recipe_version" maxlength="100" value="{{ old('recipe_version', $production->recipe_version ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Stored In <span class="text-xs text-slate-400">(optional)</span></label><input name="storage_location" maxlength="150" value="{{ old('storage_location', $production->storage_location ?? '') }}" placeholder="e.g. Finished goods store, shelf B" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div class="lg:col-span-2"><label class="mb-1 block text-sm">Quality Control Notes</label><textarea name="quality_control_notes" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('quality_control_notes', $production->quality_control_notes ?? '') }}</textarea></div>
</div>

<div class="mt-6">
    <div class="mb-2 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-800">Raw Materials Used</h3>
        <button id="addMaterialRow" type="button" class="rounded border border-slate-300 px-2 py-1 text-xs">Add Material</button>
    </div>
    <div id="materialsRows" class="space-y-2"></div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $submitLabel }}</button>
    <a href="{{ route('admin.productions.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
</div>

<script>
(() => {
    const productBarcodes = @json($productBarcodes ?? []);
    const productSelect = document.getElementById('product_id');
    const barcodeInput = document.getElementById('barcode');

    const syncBarcode = () => {
        if (!productSelect || !barcodeInput) {
            return;
        }

        const barcode = productBarcodes[productSelect.value] || '';
        barcodeInput.value = barcode;
        barcodeInput.placeholder = barcode ? '' : 'No barcode on this product';
    };

    if (productSelect) {
        productSelect.addEventListener('change', syncBarcode);
        syncBarcode();
    }

    // Best before = production date + the product's shelf life, until someone types a date.
    const shelfLife = @json($productShelfLife ?? []);
    const bestBefore = document.getElementById('best_before_date');
    const productionDate = document.querySelector('input[name="production_date"]');
    const hint = document.getElementById('shelfLifeHint');
    let bestBeforeAuto = !bestBefore?.value;
    bestBefore?.addEventListener('input', () => { bestBeforeAuto = false; });
    const syncBestBefore = () => {
        const days = shelfLife[productSelect?.value];
        if (hint) hint.textContent = days ? `Shelf life ${days} days: filled from the production date.` : 'Set a shelf life on the product to fill this automatically.';
        if (!bestBefore || !bestBeforeAuto || !days || !productionDate?.value) return;
        const date = new Date(productionDate.value + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + Number(days));
        bestBefore.value = date.toISOString().slice(0, 10);
    };
    productSelect?.addEventListener('change', syncBestBefore);
    productionDate?.addEventListener('change', syncBestBefore);
    syncBestBefore();

    const options = @json($inventoryOptions);
    const existing = @json(old('inventory_record_id', $production->inventory_record_id ?? []));
    const container = document.getElementById('materialsRows');
    const addButton = document.getElementById('addMaterialRow');

    const rowTemplate = (idx, material = {}) => {
        const selectedId = String(material.inventory_id ?? '');
        const qty = material.quantity_used ?? '';
        const optionsHtml = Object.entries(options).map(([id, name]) =>
            `<option value="${id}" ${String(id) === selectedId ? 'selected' : ''}>${name}</option>`
        ).join('');

        return `
            <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_220px_auto] material-row">
                <select name="inventory_record_id[${idx}][inventory_id]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Select Raw Material</option>
                    ${optionsHtml}
                </select>
                <input name="inventory_record_id[${idx}][quantity_used]" type="number" step="0.01" min="0.01" value="${qty}" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Quantity Used">
                <button type="button" class="remove-row rounded border border-slate-300 px-2 py-1 text-xs">Remove</button>
            </div>
        `;
    };

    const render = () => {
        const rows = container.querySelectorAll('.material-row');
        rows.forEach((row, idx) => {
            row.querySelector('select').name = `inventory_record_id[${idx}][inventory_id]`;
            row.querySelector('input').name = `inventory_record_id[${idx}][quantity_used]`;
        });
    };

    const appendRow = (material = {}) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = rowTemplate(container.querySelectorAll('.material-row').length, material);
        const row = wrapper.firstElementChild;
        row.querySelector('.remove-row').addEventListener('click', () => {
            row.remove();
            render();
        });
        container.appendChild(row);
        render();
    };

    if (Array.isArray(existing) && existing.length) existing.forEach((m) => appendRow(m));
    else appendRow({});

    addButton.addEventListener('click', () => appendRow({}));
})();
</script>
