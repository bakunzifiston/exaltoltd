<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentReviewException;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalePaymentSubmission;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalePaymentController extends Controller
{
    public function __construct(private readonly PaymentSubmissionService $payments) {}

    /**
     * Report a payment with its proof. It reaches the ledger once an approver confirms it;
     * someone who can approve payments has their own report confirmed straight away.
     */
    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeSale($request, $sale);

        $open = $sale->submittable_balance;
        $pending = $sale->pending_approval_amount;
        $method = (string) $request->input('method');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max($open, 0.01)],
            'method' => ['required', Rule::in(SalePayment::METHODS)],
            'paid_at' => ['required', 'date'],
            'reference' => [Rule::requiredIf(SalePaymentSubmission::referenceRequiredFor($method)), 'nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ] + SalePaymentSubmission::proofRules('proofs', SalePaymentSubmission::proofRequiredFor($method)), [
            'amount.max' => 'Amount exceeds the '.number_format($open, 0).' RWF still open on this sale'
                .($pending > 0 ? ' ('.number_format($pending, 0).' RWF is already awaiting approval)' : '').'.',
            'proofs.required' => 'Upload proof of payment (screenshot, slip or receipt) for '.$method.' payments.',
            'proofs.*.mimes' => 'Proof files must be images (JPG, PNG, WEBP) or PDF.',
            'proofs.*.max' => 'Each proof file must be '.(SalePaymentSubmission::PROOF_MAX_KB / 1024).' MB or smaller.',
            'reference.required' => 'Enter the '.$method.' transaction reference.',
        ]);

        // Which account received the money is the approver's call, not the submitter's.
        if (! $request->user()->canApprovePayments()) {
            unset($validated['cash_account_id']);
        }

        try {
            $submission = $this->payments->submit($sale, $validated, array_values($request->file('proofs', [])), $request->user());
        } catch (PaymentReviewException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        $amount = number_format((float) $submission->amount, 0).' RWF';
        $message = $submission->status === SalePaymentSubmission::STATUS_APPROVED
            ? $amount.' payment recorded. Balance: '.number_format($sale->refresh()->balance_due, 0).' RWF.'
            : $amount.' payment submitted for approval. You will be notified once it is approved.';

        return redirect()->route('admin.sales.show', $sale)->with('status', $message);
    }

    public function withdraw(Request $request, Sale $sale, SalePaymentSubmission $submission): RedirectResponse
    {
        abort_unless($submission->sale_id === $sale->id, 404);

        try {
            $this->payments->withdraw($submission, $request->user());
        } catch (PaymentReviewException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        return redirect()->route('admin.sales.show', $sale)->with('status', 'Payment submission withdrawn.');
    }

    public function destroy(Request $request, Sale $sale, SalePayment $payment): RedirectResponse
    {
        abort_unless($payment->sale_id === $sale->id, 404);
        // Reversing a recorded payment is reserved for owner/manager.
        abort_unless($request->user()->can('delete-records'), 403);

        $payment->delete();
        $sale->recalculatePaymentState();

        return redirect()
            ->route('admin.sales.show', $sale)
            ->with('status', 'Payment removed. Balance recalculated: '.number_format($sale->balance_due, 0).' RWF.');
    }

    /**
     * A sales user may only record payments against sales they recorded.
     */
    private function authorizeSale(Request $request, Sale $sale): void
    {
        if ($request->user()->can('manage-all-sales')) {
            return;
        }

        abort_unless($sale->created_by === $request->user()->id, 403);
    }
}
