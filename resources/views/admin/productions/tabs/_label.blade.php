<div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-sm font-semibold text-slate-800">Batch labels</h3>
        <p class="text-sm text-slate-600">Printable labels carrying the product, batch number, production date, best-before / use-by dates and a QR code that opens this batch's trace page (sign-in required).</p>
    </div>
    @if (! $production->best_before_date && ! $production->expiry_date)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This batch has no best-before or use-by date. Add one on the batch (or set a shelf life on the product) before printing labels.</p>
    @endif
    <form method="GET" action="{{ route('admin.productions.label', $production) }}" target="_blank" class="flex flex-wrap items-end gap-3">
        <label class="text-sm text-slate-600">Number of labels<input type="number" name="copies" min="1" max="60" value="12" class="mt-1 block w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Open printable labels</button>
    </form>
</div>
