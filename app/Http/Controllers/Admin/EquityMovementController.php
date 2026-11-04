<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\EquityMovement;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Capital the owner puts into the business and takes out of it. */
class EquityMovementController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance.equity-movements', [
            'movements' => EquityMovement::query()->with('cashAccount')
                ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(TablePageSize::resolve($request, 25))->withQueryString(),
            'types' => EquityMovement::TYPES,
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EquityMovement::create($request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(EquityMovement::TYPES))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999', 'decimal:0,2'],
            'occurred_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]) + ['recorded_by' => $request->user()->id]);

        return back()->with('status', 'Equity movement recorded.');
    }

    public function destroy(EquityMovement $equityMovement): RedirectResponse
    {
        $equityMovement->delete();

        return back()->with('status', 'Equity movement removed.');
    }
}
