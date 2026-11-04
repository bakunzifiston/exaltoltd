@csrf

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@php
    $paymentOptions = isset($sale) && $sale->payment_status === 'Partially Paid'
        ? ['Paid', 'Partially Paid', 'Pending', 'Credit']
        : ['Paid', 'Pending', 'Credit'];
    $channelOptions = isset($sale) && $sale->sales_channel === 'Online Store'
        ? ['Online Store', 'Momo Pay', 'Card', 'Cash']
        : ['Momo Pay', 'Card', 'Cash'];
    $existingItems = old('items', isset($sale)
        ? $sale->items
            ->map(fn ($i) => [
                'product_id' => $i->product_id,
                'production_id' => $i->production_id,
                'quantity_sold' => $i->quantity_sold,
                'unit_price' => $i->unit_price,
            ])
            ->values()
            ->all()
        : []);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="relative" id="customerNameWrap">
        <label for="customer_name" class="mb-1 block text-sm">Customer Name</label>
        <input
            id="customer_name"
            name="customer_name"
            type="text"
            value="{{ old('customer_name', $sale->customer_name ?? '') }}"
            required
            autocomplete="off"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
        >
        <ul
            id="customer_name_suggestions"
            class="absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            role="listbox"
            aria-label="Customer name suggestions"
        ></ul>
    </div>
    <div><label class="mb-1 block text-sm">Customer Phone</label><input name="customer_Phone" value="{{ old('customer_Phone', $sale->customer_Phone ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Customer Email</label><input name="customer_email" type="email" value="{{ old('customer_email', $sale->customer_email ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Delivery Address</label><input name="delivery_address" value="{{ old('delivery_address', $sale->delivery_address ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Invoice Number</label><input name="invoice_number" value="{{ old('invoice_number', $sale->invoice_number ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="mb-1 block text-sm">Sale Date</label><input name="sale_date" type="date" required value="{{ old('sale_date', isset($sale) ? \Illuminate\Support\Carbon::parse($sale->sale_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div>
        <label class="mb-1 block text-sm">Payment Due Date <span class="text-xs text-slate-400">(for credit / pending sales)</span></label>
        <input name="due_date" type="date" value="{{ old('due_date', isset($sale) && $sale->due_date ? $sale->due_date->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-sm">Payment Status</label>
        <select id="payment_status" name="payment_status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($paymentOptions as $status)<option value="{{ $status }}" @selected(old('payment_status', $sale->payment_status ?? '') === $status)>{{ $status }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Partial payments are recorded from the sale page, not here.</p>
    </div>
    <div id="paidProofSection" class="hidden rounded-lg border border-teal-200 bg-teal-50/50 p-4 md:col-span-2">
        <p class="text-sm font-semibold text-teal-900">Proof of payment</p>
        <p class="mt-0.5 text-xs text-teal-800/80">
            @if (auth()->user()->canApprovePayments())
                The open balance is recorded as paid, with this proof kept on file.
            @else
                The open balance is sent for approval with this proof. The sale shows as paid once an approver confirms it — you'll get an email.
            @endif
        </p>
        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="payment_reference" class="mb-1 block text-sm">Transaction reference <span class="text-xs text-slate-400">(required for MoMo)</span></label>
                <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="191" placeholder="MoMo TX ID, receipt no..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="payment_proofs" class="mb-1 block text-sm">Proof file(s) <span class="text-xs text-slate-400">(optional for cash)</span></label>
                <input id="payment_proofs" name="payment_proofs[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-xs file:font-medium file:text-white">
                <p class="mt-1 text-[11px] text-slate-400">Up to {{ \App\Models\SalePaymentSubmission::MAX_PROOFS }} files · JPG, PNG, WEBP or PDF · {{ \App\Models\SalePaymentSubmission::PROOF_MAX_KB / 1024 }} MB each</p>
            </div>
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm">Delivery Status</label>
        <select name="delivery_status" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach(['Delivered','Pending','In Transit','Returned'] as $status)<option value="{{ $status }}" @selected(old('delivery_status', $sale->delivery_status ?? '') === $status)>{{ $status }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm">Sales Channel</label>
        <select name="sales_channel" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($channelOptions as $channel)<option value="{{ $channel }}" @selected(old('sales_channel', $sale->sales_channel ?? '') === $channel)>{{ $channel }}</option>@endforeach
        </select>
    </div>
</div>

<div class="mt-6">
    <div class="mb-2 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-800">Products</h3>
        <button id="addItemRow" type="button" class="rounded border border-slate-300 px-2 py-1 text-xs">Add Product</button>
    </div>
    <div id="saleItemsRows" class="space-y-2"></div>
    <p class="mt-3 text-sm text-slate-600">Invoice Total: <span id="invoiceTotal">0 RWF</span></p>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $submitLabel }}</button>
    <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
</div>

<script>
(() => {
    const products = @json($products);
    const productions = @json($productions);
    const existing = @json($existingItems);
    const container = document.getElementById('saleItemsRows');
    const addButton = document.getElementById('addItemRow');
    const totalLabel = document.getElementById('invoiceTotal');

    const productOptions = products.map(p => `<option value="${p.id}">${p.type}</option>`).join('');

    const batchesFor = (productId, selected='') => {
        return productions.filter(b => String(b.product_id) === String(productId))
            .map(b => `<option value="${b.id}" ${String(b.id)===String(selected)?'selected':''}>${b.batch_id}</option>`)
            .join('');
    };

    const computeTotal = () => {
        let total = 0;
        container.querySelectorAll('.sale-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty').value || 0);
            const price = parseFloat(row.querySelector('.price').value || 0);
            const line = Math.round((qty * price) * 100) / 100;
            row.querySelector('.line-total').value = line.toFixed(2);
            total += line;
        });
        totalLabel.textContent = `${Math.round(total).toLocaleString()} RWF`;
    };

    const renderRow = (idx, item = {}) => {
        const productId = item.product_id ?? '';
        const productionId = item.production_id ?? '';
        const qty = item.quantity_sold ?? '';
        const price = item.unit_price ?? '';
        return `
            <div class="sale-row grid grid-cols-1 gap-2 md:grid-cols-[1fr_1fr_170px_170px_170px_auto]">
                <select name="items[${idx}][product_id]" class="product-select rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Product</option>
                    ${products.map(p => `<option value="${p.id}" ${String(p.id)===String(productId)?'selected':''}>${p.type}</option>`).join('')}
                </select>
                <select name="items[${idx}][production_id]" class="batch-select rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <option value="">Batch</option>${batchesFor(productId, productionId)}
                </select>
                <input name="items[${idx}][quantity_sold]" value="${qty}" type="number" step="0.01" min="0.01" class="qty rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Qty" required>
                <input name="items[${idx}][unit_price]" value="${price}" type="number" step="0.01" min="0" class="price rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Price" required>
                <input class="line-total rounded-lg border border-slate-300 px-3 py-2 text-sm bg-slate-50" readonly placeholder="Total">
                <button type="button" class="remove-row rounded border border-slate-300 px-2 py-1 text-xs">Remove</button>
            </div>
        `;
    };

    const reindex = () => {
        container.querySelectorAll('.sale-row').forEach((row, idx) => {
            row.querySelector('.product-select').name = `items[${idx}][product_id]`;
            row.querySelector('.batch-select').name = `items[${idx}][production_id]`;
            row.querySelector('.qty').name = `items[${idx}][quantity_sold]`;
            row.querySelector('.price').name = `items[${idx}][unit_price]`;
        });
    };

    const attachRowHandlers = (row) => {
        row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); reindex(); computeTotal(); });
        row.querySelector('.qty').addEventListener('blur', computeTotal);
        row.querySelector('.price').addEventListener('blur', computeTotal);
        row.querySelector('.product-select').addEventListener('change', (e) => {
            const batch = row.querySelector('.batch-select');
            batch.innerHTML = `<option value="">Batch</option>${batchesFor(e.target.value)}`;
        });
    };

    const appendRow = (item = {}) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = renderRow(container.querySelectorAll('.sale-row').length, item);
        const row = wrapper.firstElementChild;
        attachRowHandlers(row);
        container.appendChild(row);
        reindex();
        computeTotal();
    };

    if (Array.isArray(existing) && existing.length) existing.forEach(appendRow); else appendRow({});
    addButton.addEventListener('click', () => appendRow({}));

    // --- Customer Name autocomplete ---
    function comboBox(inputId, listId, wrapId, url) {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        let timer = null, active = -1, items = [];

        function render(data) {
            items = data; active = -1;
            if (!data.length) { list.classList.add('hidden'); return; }
            list.innerHTML = data.map((n, i) =>
                `<li role="option" data-i="${i}" class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-teal-50">${n.replace(/</g, '&lt;')}</li>`
            ).join('');
            list.classList.remove('hidden');
        }
        function close() { list.classList.add('hidden'); items = []; active = -1; }
        function pick(n) { input.value = n; close(); }
        function highlight(i) {
            const lis = list.querySelectorAll('li');
            lis.forEach(l => l.classList.remove('bg-teal-50', 'font-semibold'));
            if (i >= 0 && i < lis.length) { active = i; lis[i].classList.add('bg-teal-50', 'font-semibold'); lis[i].scrollIntoView({ block: 'nearest' }); }
        }
        function query() {
            const q = input.value.trim();
            if (!q.length) { close(); return; }
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
    }

    comboBox('customer_name', 'customer_name_suggestions', 'customerNameWrap', `{{ route('admin.sales.customer-names') }}`);

    // --- "Paid" needs proof (sent for approval) ---
    const statusSelect = document.getElementById('payment_status');
    const channelSelect = document.querySelector('select[name="sales_channel"]');
    const proofSection = document.getElementById('paidProofSection');
    const proofInput = document.getElementById('payment_proofs');
    const referenceInput = document.getElementById('payment_reference');
    const syncProof = () => {
        const paid = statusSelect.value === 'Paid';
        proofSection.classList.toggle('hidden', !paid);
        // Editing an already settled sale needs no new proof; the server decides from the balance.
        const settled = @json(isset($sale) && $sale->submittable_balance <= 0);
        proofInput.required = paid && !settled && channelSelect.value !== 'Cash';
        referenceInput.required = paid && !settled && channelSelect.value === 'Momo Pay';
    };
    statusSelect.addEventListener('change', syncProof);
    channelSelect.addEventListener('change', syncProof);
    syncProof();
})();
</script>
