<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentReviewException;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\SalePaymentSubmission;
use App\Services\PaymentSubmissionService;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The approver's queue: check reported payments against the receiving account, then
 * approve (writes the ledger) or reject (sales person is told why).
 */
class PaymentApprovalController extends Controller
{
    public function __construct(private readonly PaymentSubmissionService $payments) {}

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), SalePaymentSubmission::STATUSES)
            ? $request->query('status')
            : SalePaymentSubmission::STATUS_PENDING;

        $submissions = SalePaymentSubmission::query()
            ->with(['sale:id,sales_id,customer_name,total_revenue,sales_channel', 'submitter:id,name', 'reviewer:id,name'])
            ->withCount('proofs')
            ->where('status', $status)
            // Oldest first while waiting, so nothing sits at the bottom of the pile.
            ->when($status === SalePaymentSubmission::STATUS_PENDING,
                fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                fn ($q) => $q->orderByDesc('reviewed_at')->orderByDesc('updated_at')->orderByDesc('id'))
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $counts = SalePaymentSubmission::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingTotal = (float) SalePaymentSubmission::query()->pending()->sum('amount');

        return view('admin.payment-approvals.index', compact('submissions', 'status', 'counts', 'pendingTotal'));
    }

    public function show(SalePaymentSubmission $submission): View
    {
        $submission->load(['sale.customer', 'sale.creator', 'submitter', 'reviewer', 'proofs', 'cashAccount', 'payment']);

        return view('admin.payment-approvals.show', [
            'submission' => $submission,
            'sale' => $submission->sale,
            'warnings' => $this->payments->duplicateWarnings($submission),
            'cashAccounts' => $this->cashAccounts(),
        ]);
    }

    public function approve(Request $request, SalePaymentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->payments->approve(
                $submission,
                $request->user(),
                isset($validated['cash_account_id']) ? (int) $validated['cash_account_id'] : null,
                $validated['review_note'] ?? null,
            );
        } catch (PaymentReviewException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', number_format((float) $submission->amount, 0).' RWF payment for '
            .$submission->sale?->sales_id.' approved. The sales person has been notified.');
    }

    public function reject(Request $request, SalePaymentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_note.required' => 'Give a reason so the sales person knows what to fix.',
            'review_note.min' => 'Give a reason so the sales person knows what to fix.',
        ]);

        try {
            $this->payments->reject($submission, $request->user(), $validated['review_note']);
        } catch (PaymentReviewException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment for '.$submission->sale?->sales_id.' rejected. The sales person has been notified.');
    }

    private function cashAccounts()
    {
        return CashAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);
    }
}
