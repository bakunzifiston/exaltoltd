<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The cash, bank and mobile-money accounts every payment is attributed to. */
class CashAccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance.cash-accounts', [
            'accounts' => CashAccount::query()->orderBy('name')->paginate(TablePageSize::resolve($request, 25))->withQueryString(),
            'types' => CashAccount::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CashAccount::create($this->validated($request));

        return back()->with('status', 'Cash account added.');
    }

    public function update(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $cashAccount->update($this->validated($request));

        return back()->with('status', 'Cash account updated.');
    }

    public function destroy(CashAccount $cashAccount): RedirectResponse
    {
        $cashAccount->delete();

        return back()->with('status', 'Cash account removed. Payments already attributed to it keep their history.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_keys(CashAccount::TYPES))],
            'account_number' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'between:-999999999999,999999999999', 'decimal:0,2'],
            'opening_balance_date' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
