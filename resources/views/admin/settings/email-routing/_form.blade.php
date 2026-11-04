<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Event <span class="text-rose-500">*</span></label>
    <select name="event" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20" required>
        <option value="">Select event…</option>
        @foreach($events as $key => $label)
            <option value="{{ $key }}" @selected(old('event', $rule?->event) === $key)>{{ $label }}</option>
        @endforeach
    </select>
    @error('event')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Recipient Email <span class="text-rose-500">*</span></label>
    <input name="recipient_email" type="email" value="{{ old('recipient_email', $rule?->recipient_email) }}"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
        placeholder="orders@example.com" maxlength="200" required>
    @error('recipient_email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Recipient Name <span class="text-slate-400 font-normal">(optional)</span></label>
    <input name="recipient_name" type="text" value="{{ old('recipient_name', $rule?->recipient_name) }}"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
        placeholder="Orders Team" maxlength="120">
    @error('recipient_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true))
            class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
        <span class="text-sm text-slate-700">Active — uncheck to temporarily pause delivery to this address</span>
    </label>
</div>
