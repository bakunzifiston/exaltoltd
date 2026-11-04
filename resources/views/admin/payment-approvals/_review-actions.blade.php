{{-- Approve / reject a pending submission. Expects $submission and $cashAccounts. --}}
@if ($submission->isPending())
    @can('approve-payments')
        @if ($submission->submitted_by === auth()->id())
            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">You submitted this payment — another approver must review it.</p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.payment-approvals.approve', $submission) }}" class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3"
                      onsubmit="return confirm('Approve {{ number_format((float) $submission->amount, 0) }} RWF as received?')">
                    @csrf
                    @if ($cashAccounts->isNotEmpty())
                        <label class="block text-xs font-medium text-slate-600">Received into
                            <select name="cash_account_id" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">— Not specified —</option>
                                @foreach ($cashAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) old('cash_account_id', $submission->cash_account_id) === $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <input name="review_note" maxlength="1000" placeholder="Note (optional)" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <button type="submit" class="w-full rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-800">Approve payment</button>
                </form>
                <form method="POST" action="{{ route('admin.payment-approvals.reject', $submission) }}" class="space-y-2 rounded-lg border border-rose-200 bg-rose-50 p-3">
                    @csrf
                    <label class="block text-xs font-medium text-slate-600">Reason for rejecting <span class="text-rose-600">*</span>
                        <textarea name="review_note" rows="2" required minlength="5" maxlength="1000" placeholder="e.g. Amount on the screenshot doesn't match, TX ID not found..." class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></textarea>
                    </label>
                    <button class="w-full rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Reject</button>
                </form>
            </div>
        @endif
    @endcan
@endif
