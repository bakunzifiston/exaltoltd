@csrf

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name <span class="text-rose-600">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
    </div>

    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email <span class="text-rose-600">*</span></label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
    </div>

    <div>
        <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
        <select id="role" name="role" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role ?? 'sales') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Owner: full access · Manager: operations &amp; reports · Sales: own sales only · Production: own batches only.</p>
    </div>

    <div>
        <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}" maxlength="50"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
    </div>

    <div>
        <label for="employee_id" class="mb-1 block text-sm font-medium text-slate-700">Linked employee (HR record)</label>
        <select id="employee_id" name="employee_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <option value="">— None —</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((int) old('employee_id', $user->employee_id ?? 0) === $employee->id)>
                    {{ $employee->name }}{{ $employee->position ? ' — ' . $employee->position : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">
            Password @isset($user)<span class="text-xs font-normal text-slate-400">(leave blank to keep current)</span>@else<span class="text-rose-600">*</span>@endisset
        </label>
        <input id="password" name="password" type="password" maxlength="255" {{ isset($user) ? '' : 'required' }} autocomplete="new-password"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
    </div>

    <div>
        <label for="sales_target" class="mb-1 block text-sm font-medium text-slate-700">Monthly sales target (RWF)</label>
        <input id="sales_target" name="sales_target" type="number" step="0.01" min="0" value="{{ old('sales_target', $user->sales_target ?? '') }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none" placeholder="e.g. 500000">
        <p class="mt-1 text-xs text-slate-400">Used in Team Performance (target vs actual). For sales staff.</p>
    </div>

    <div>
        <label for="commission_rate" class="mb-1 block text-sm font-medium text-slate-700">Commission rate (%)</label>
        <input id="commission_rate" name="commission_rate" type="number" step="0.01" min="0" max="100" value="{{ old('commission_rate', $user->commission_rate ?? '') }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none" placeholder="e.g. 5">
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="can_approve_payments" value="0">
            <input type="checkbox" name="can_approve_payments" value="1" @checked(old('can_approve_payments', $user->can_approve_payments ?? false)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Can approve payments
        </label>
        <p class="mt-1 text-xs text-slate-400">Receives an email when a payment is reported with proof, and confirms it was received. Owners can always approve. Nobody can approve a payment they reported themselves.</p>
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300 text-teal-700 focus:ring-teal-600">
            Account is active (can log in)
        </label>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">{{ $submitLabel }}</button>
    <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</a>
</div>
