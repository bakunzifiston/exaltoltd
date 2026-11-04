@csrf

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="relative" id="supplierNameWrap">
        <label for="supplier_name" class="mb-1 block text-sm">Supplier Name</label>
        <input
            id="supplier_name"
            name="supplier_name"
            type="text"
            value="{{ old('supplier_name', $inventoryRecord->supplier_name ?? '') }}"
            required
            autocomplete="off"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        >
        <ul
            id="supplier_name_suggestions"
            class="absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            role="listbox"
            aria-label="Supplier name suggestions"
        ></ul>
        @error('supplier_name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div><label class="mb-1 block text-sm">Invoice Number</label><input name="invoice_number" value="{{ old('invoice_number', $inventoryRecord->invoice_number ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Item Type</label>
        <select id="item_type" name="item_type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="Product" @selected(old('item_type', $inventoryRecord->item_type ?? '') === 'Product')>Product</option>
            <option value="Raw Material" @selected(old('item_type', $inventoryRecord->item_type ?? '') === 'Raw Material')>Raw Material</option>
        </select>
    </div>
    <div class="relative" id="itemNameWrap">
        <label for="item_name" class="mb-1 block text-sm">Item Name</label>
        <input
            id="item_name"
            name="item_name"
            type="text"
            value="{{ old('item_name', $inventoryRecord->item_name ?? '') }}"
            required
            autocomplete="off"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        >
        <ul
            id="item_name_suggestions"
            class="absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            role="listbox"
            aria-label="Item name suggestions"
        ></ul>
        @error('item_name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div><label class="mb-1 block text-sm">Lot / Batch No. <span class="text-xs text-slate-400">(optional)</span></label><input name="lot_number" value="{{ old('lot_number', $inventoryRecord->lot_number ?? '') }}" placeholder="Supplier lot for tracing" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Supplier's Lot No. <span class="text-xs text-slate-400">(optional)</span></label><input name="supplier_lot_number" maxlength="100" value="{{ old('supplier_lot_number', $inventoryRecord->supplier_lot_number ?? '') }}" placeholder="As printed on the supplier's pack" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Inspection on receipt</label>
        <select name="inspection_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach (\App\Models\InventoryRecord::INSPECTION_STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('inspection_status', $inventoryRecord->inspection_status ?? 'approved') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Rejected deliveries cannot be used in production.</p>
    </div>
    <div><label class="mb-1 block text-sm">Certificate of Analysis ref. <span class="text-xs text-slate-400">(optional)</span></label><input name="coa_reference" maxlength="100" value="{{ old('coa_reference', $inventoryRecord->coa_reference ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Expiry Date <span class="text-xs text-slate-400">(optional)</span></label><input name="expiry_date" type="date" value="{{ old('expiry_date', isset($inventoryRecord) && $inventoryRecord->expiry_date ? $inventoryRecord->expiry_date->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Reorder Level <span class="text-xs text-slate-400">(low-stock alert)</span></label><input name="reorder_level" type="number" step="0.01" min="0" value="{{ old('reorder_level', $inventoryRecord->reorder_level ?? '') }}" placeholder="Alert when on-hand falls below" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div id="productWrap">
        <label class="mb-1 block text-sm">Link to Product</label>
        <select name="product_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Select product</option>
            @foreach ($products as $id => $label)
                <option value="{{ $id }}" @selected((string) old('product_id', $inventoryRecord->product_id ?? '') === (string) $id)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm">Quantity in</label>
        <input id="quantity_in" name="quantity_in" type="number" step="0.01" min="0" value="{{ old('quantity_in', $inventoryRecord->quantity_in ?? 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-slate-500">Amount received from the supplier.</p>
    </div>
    <div>
        <label class="mb-1 block text-sm">Quantity out</label>
        @if (isset($inventoryRecord))
            <input type="text" id="quantity_out_display" readonly value="{{ number_format((float) old('quantity_out', $inventoryRecord->quantity_out ?? 0), 2) }} L" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            <input type="hidden" id="quantity_out" name="quantity_out" value="{{ old('quantity_out', $inventoryRecord->quantity_out ?? 0) }}">
            <p class="mt-1 text-xs text-slate-500">Updated automatically when production uses this item.</p>
        @else
            <input type="hidden" id="quantity_out" name="quantity_out" value="0">
            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">0.00 L</p>
            <p class="mt-1 text-xs text-slate-500">Starts at zero — production increases this when materials are used.</p>
        @endif
    </div>
    <div>
        <label class="mb-1 block text-sm">On hand</label>
        <p id="on_hand_preview" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-teal-800">0.00 L</p>
        <p class="mt-1 text-xs text-slate-500">In minus out (available stock).</p>
    </div>
    <div><label class="mb-1 block text-sm">Unit Cost (RWF)</label><input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0" @required(!isset($inventoryRecord)) value="{{ old('unit_cost', $inventoryRecord->unit_cost ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@error('unit_cost')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><label class="mb-1 block text-sm">Damaged</label><input name="damaged" type="number" step="0.01" value="{{ old('damaged', $inventoryRecord->damaged ?? 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Storage Location</label><input name="storage_location" value="{{ old('storage_location', $inventoryRecord->storage_location ?? '') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Record Date</label><input name="record_date" type="date" value="{{ old('record_date', isset($inventoryRecord) && $inventoryRecord->record_date ? $inventoryRecord->record_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Total Amount (RWF)</label><input id="total_amount" name="total_amount" type="number" step="0.01" @readonly(!isset($inventoryRecord)) value="{{ old('total_amount', $inventoryRecord->total_amount ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@if(!isset($inventoryRecord))<p class="mt-1 text-xs text-slate-500">Automatically calculated: On hand × Unit Cost.</p>@endif</div>
    <div><label class="mb-1 block text-sm">Amount Paid (RWF)</label><input id="amount_paid" name="amount_paid" type="number" step="0.01" value="{{ old('amount_paid', $inventoryRecord->amount_paid ?? 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Payment Status</label>
        <select id="payment_status" name="payment_status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="Paid" @selected(old('payment_status', $inventoryRecord->payment_status ?? 'Unpaid') === 'Paid')>Paid</option>
            <option value="Partial" @selected(old('payment_status', $inventoryRecord->payment_status ?? 'Unpaid') === 'Partial')>Partially Paid</option>
            <option value="Unpaid" @selected(old('payment_status', $inventoryRecord->payment_status ?? 'Unpaid') === 'Unpaid')>Unpaid</option>
        </select>
    </div>
    <div><label class="mb-1 block text-sm">Payment Due Date</label><input name="payment_due_date" type="date" value="{{ old('payment_due_date', isset($inventoryRecord) && $inventoryRecord->payment_due_date ? $inventoryRecord->payment_due_date->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $submitLabel }}</button>
    <a href="{{ route('admin.inventory-records.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
</div>

<script>
(() => {
    const itemTypeEl = document.getElementById('item_type');
    const productWrap = document.getElementById('productWrap');
    const qtyInEl = document.getElementById('quantity_in');
    const qtyOutEl = document.getElementById('quantity_out');
    const onHandEl = document.getElementById('on_hand_preview');
    const totalEl = document.getElementById('total_amount');
    const paidEl = document.getElementById('amount_paid');
    const statusEl = document.getElementById('payment_status');
    const unitCostEl = document.getElementById('unit_cost');
    const calculateTotal = @json(!isset($inventoryRecord));

    const syncItemType = () => productWrap.style.display = itemTypeEl.value === 'Product' ? 'block' : 'none';
    const syncOnHand = () => {
        if (!onHandEl || !qtyInEl || !qtyOutEl) return;
        const onHand = Math.max(0, parseFloat(qtyInEl.value || 0) - parseFloat(qtyOutEl.value || 0));
        onHandEl.textContent = onHand.toFixed(2) + ' L';
    };
    const syncCostAndStatus = () => {
        const qty = parseFloat(qtyInEl.value || 0);
        if (calculateTotal) {
            const onHand = Math.max(0, qty - parseFloat(qtyOutEl.value || 0));
            totalEl.value = (onHand * parseFloat(unitCostEl.value || 0)).toFixed(2);
        }
        const total = parseFloat(totalEl.value || 0);
        const paid = parseFloat(paidEl.value || 0);
        if (!calculateTotal && qty > 0 && total > 0) unitCostEl.value = (total / qty).toFixed(2);
        if (total <= 0) statusEl.value = 'Unpaid';
        else if (paid >= total) statusEl.value = 'Paid';
        else if (paid > 0) statusEl.value = 'Partial';
        else statusEl.value = 'Unpaid';
    };
    syncItemType();
    syncOnHand();
    syncCostAndStatus();
    itemTypeEl.addEventListener('change', syncItemType);
    qtyInEl.addEventListener('input', syncOnHand);
    qtyInEl.addEventListener('blur', syncCostAndStatus);
    totalEl.addEventListener('blur', syncCostAndStatus);
    paidEl.addEventListener('blur', syncCostAndStatus);
    if (calculateTotal) {
        [qtyInEl, qtyOutEl, unitCostEl, paidEl].forEach(el => el.addEventListener('input', syncCostAndStatus));
    }

    // --- Reusable combo-box autocomplete ---
    function comboBox(inputId, listId, wrapId, url, guardFn) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        let timer = null, active = -1, items = [];

        function render(data) {
            items = data; active = -1;
            if (!data.length) { list.classList.add('hidden'); return; }
            list.innerHTML = data.map((n, i) =>
                `<li role="option" data-i="${i}" class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-[#012b51]/10">${n.replace(/</g, '&lt;')}</li>`
            ).join('');
            list.classList.remove('hidden');
        }
        function close() { list.classList.add('hidden'); items = []; active = -1; }
        function pick(n) { input.value = n; close(); }
        function highlight(i) {
            const lis = list.querySelectorAll('li');
            lis.forEach(l => l.classList.remove('bg-[#012b51]/10', 'font-semibold'));
            if (i >= 0 && i < lis.length) { active = i; lis[i].classList.add('bg-[#012b51]/10', 'font-semibold'); lis[i].scrollIntoView({ block: 'nearest' }); }
        }
        function query() {
            const q = input.value.trim();
            if ((guardFn && !guardFn()) || !q.length) { close(); return; }
            fetch(`${url}?q=${encodeURIComponent(q)}`).then(r => r.json())
                .then(d => render(d.filter(n => n.toLowerCase() !== q.toLowerCase())))
                .catch(() => close());
        }
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(query, 200); });
        input.addEventListener('focus', () => { if (input.value.trim().length) query(); });
        input.addEventListener('keydown', (e) => {
            if (list.classList.contains('hidden')) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, items.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(items[active]); }
            else if (e.key === 'Escape') close();
        });
        list.addEventListener('mousedown', (e) => { const li = e.target.closest('li'); if (li) { e.preventDefault(); pick(items[+li.dataset.i]); } });
        document.addEventListener('click', (e) => { if (!e.target.closest('#' + wrapId)) close(); });
        return { close };
    }

    const supplierCombo = comboBox('supplier_name', 'supplier_name_suggestions', 'supplierNameWrap', `{{ route('admin.inventory-records.supplier-names') }}`);
    const itemNameCombo = comboBox('item_name', 'item_name_suggestions', 'itemNameWrap', `{{ route('admin.inventory-records.item-names') }}`, () => itemTypeEl.value === 'Raw Material');
    itemTypeEl.addEventListener('change', () => itemNameCombo.close());
})();
</script>
