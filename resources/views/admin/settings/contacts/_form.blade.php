@php
    $typeHints = [
        'phone'    => 'e.g. +250786931872 — used in tel: links',
        'email'    => 'e.g. info@shakyltd.rw',
        'whatsapp' => 'digits only, no + — e.g. 250786931872',
        'address'  => 'Full address text, e.g. Kamonyi District, Southern Province, Rwanda',
        'social'   => 'Full URL to the social profile or message link',
    ];
    $selectedType = old('type', $channel?->type ?? '');
@endphp

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-rose-500">*</span></label>
    <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20" required>
        <option value="">Select type…</option>
        @foreach($types as $t)
            <option value="{{ $t }}" @selected($selectedType === $t)>{{ ucfirst($t) }}</option>
        @endforeach
    </select>
    @foreach($typeHints as $t => $hint)
        <p class="mt-1 text-xs text-slate-400 type-hint hidden" data-type="{{ $t }}">{{ $hint }}</p>
    @endforeach
    @error('type')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Label <span class="text-rose-500">*</span></label>
    <input name="label" type="text" value="{{ old('label', $channel?->label) }}"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
        placeholder="e.g. Main Line, General Inquiries" maxlength="80" required>
    @error('label')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Value <span class="text-rose-500">*</span></label>
    <input name="value" type="text" value="{{ old('value', $channel?->value) }}"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
        maxlength="500" required>
    @error('value')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-3 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
        <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $channel?->sort_order ?? 0) }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20">
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_primary" value="0">
            <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $channel?->is_primary))
                class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            <span class="text-sm text-slate-700">Primary</span>
        </label>
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $channel?->is_active ?? true))
                class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            <span class="text-sm text-slate-700">Active</span>
        </label>
    </div>
</div>

<script>
(function () {
    const sel = document.querySelector('select[name="type"]');
    const hints = document.querySelectorAll('.type-hint');
    function update() {
        hints.forEach(h => h.classList.toggle('hidden', h.dataset.type !== sel.value));
    }
    sel.addEventListener('change', update);
    update();
}());
</script>
