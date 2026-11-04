<x-layouts.admin title="Cash accounts">
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'cash-accounts', 'heading' => 'Cash accounts', 'intro' => 'Cash on hand, bank and mobile-money accounts that payments in and out are attributed to.'])

        <form method="POST" action="{{ route('admin.finance.cash-accounts.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            @csrf
            <h3 class="font-semibold text-slate-800 md:col-span-3">Add an account</h3>
            <label class="text-sm text-slate-600">Name<input name="name" required maxlength="150" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Type<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Account number<input name="account_number" maxlength="100" value="{{ old('account_number') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Opening balance (RWF)<input type="number" step="0.01" name="opening_balance" required value="{{ old('opening_balance', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Opening balance date<input type="date" name="opening_balance_date" value="{{ old('opening_balance_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-600"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
            <label class="text-sm text-slate-600 md:col-span-3">Notes<textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></label>
            <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Add account</button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table data-bulk="{{ route('admin.bulk-delete', 'cash-accounts') }}" class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Account</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Number</th><th class="px-4 py-3 text-right">Opening balance</th><th class="px-4 py-3">Since</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $account->name }}</td>
                            <td class="px-4 py-3">{{ $account->typeLabel() }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $account->account_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $account->opening_balance, 2) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $account->opening_balance_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $account->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3 text-right">
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-xs font-medium text-teal-700">Edit</summary>
                                    <form method="POST" action="{{ route('admin.finance.cash-accounts.update', $account) }}" class="mt-2 grid w-72 gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                        @csrf @method('PUT')
                                        <input name="name" required maxlength="150" value="{{ $account->name }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <select name="type" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected($account->type === $value)>{{ $label }}</option>@endforeach</select>
                                        <input name="account_number" maxlength="100" value="{{ $account->account_number }}" placeholder="Account number" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="number" step="0.01" name="opening_balance" required value="{{ $account->opening_balance }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="date" name="opening_balance_date" value="{{ $account->opening_balance_date?->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <label class="flex items-center gap-2 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Active</label>
                                        <textarea name="notes" rows="2" maxlength="2000" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">{{ $account->notes }}</textarea>
                                        <button type="submit" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800">Save</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.finance.cash-accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Remove this account?')">@csrf @method('DELETE')<button type="submit" class="ml-2 text-xs font-medium text-rose-700">Remove</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No cash accounts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$accounts" label="accounts" />
    </section>
</x-layouts.admin>
