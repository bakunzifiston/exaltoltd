<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Support\TablePageSize;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Borrowings and their repayments.
 *
 * The principal and interest split recorded on each repayment is what lets one payment
 * feed two statements correctly: interest is an expense on the income statement, while
 * principal is a financing outflow and a reduction of the liability.
 */
class LoanController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance.loans', [
            'loans' => Loan::query()->withCount('repayments')->orderByDesc('start_date')->paginate(TablePageSize::resolve($request, 25))->withQueryString(),
            'repayments' => LoanRepayment::query()->with('loan')->orderByDesc('paid_at')->limit(50)->get(),
            'asOf' => CarbonImmutable::now()->endOfMonth(),
            'statuses' => Loan::STATUSES,
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Loan::create($request->validate([
            'lender' => ['required', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:150'],
            'principal' => ['required', 'numeric', 'min:0.01', 'max:999999999999', 'decimal:0,2'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rate_basis' => ['required', 'in:annual,monthly,flat'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'repayment_frequency' => ['required', 'in:monthly,quarterly,annual,irregular'],
            'status' => ['required', 'in:'.implode(',', array_keys(Loan::STATUSES))],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('status', 'Loan recorded. The drawdown appears as a financing inflow on the start date.');
    }

    public function destroy(Loan $loan): RedirectResponse
    {
        $loan->delete();

        return back()->with('status', 'Loan removed.');
    }

    public function storeRepayment(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999', 'decimal:0,2'],
            'principal_portion' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'interest_portion' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        // The split must account for the whole payment, or the income statement and the
        // cash flow statement would disagree about what left the bank.
        $split = round((float) $data['principal_portion'] + (float) $data['interest_portion'], 2);
        if (abs($split - (float) $data['total_amount']) >= 0.01) {
            throw ValidationException::withMessages([
                'total_amount' => 'Principal plus interest is RWF '.number_format($split, 2)
                    .', which does not equal the total paid of RWF '.number_format((float) $data['total_amount'], 2).'.',
            ]);
        }

        $outstanding = $loan->outstandingAt(CarbonImmutable::parse($data['paid_at']));
        if ((float) $data['principal_portion'] > $outstanding + 0.01) {
            throw ValidationException::withMessages([
                'principal_portion' => 'That is more principal than is outstanding on this loan at that date (RWF '
                    .number_format($outstanding, 2).').',
            ]);
        }

        $loan->repayments()->create($data + ['recorded_by' => $request->user()->id]);

        return back()->with('status', 'Repayment recorded. Interest goes to the income statement, principal to financing.');
    }

    public function destroyRepayment(LoanRepayment $loanRepayment): RedirectResponse
    {
        $loanRepayment->delete();

        return back()->with('status', 'Repayment removed.');
    }
}
