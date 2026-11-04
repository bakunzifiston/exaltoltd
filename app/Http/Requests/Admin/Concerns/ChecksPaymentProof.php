<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalePaymentSubmission;
use Illuminate\Validation\Validator;

trait ChecksPaymentProof
{
    /** @return array<string, list<string>> */
    protected function paymentProofRules(): array
    {
        return SalePaymentSubmission::proofRules('payment_proofs', false) + [
            'payment_reference' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Choosing "Paid" reports the open balance as a payment, so it needs the same evidence as
     * a payment recorded from the sale page. Nothing is needed when nothing is left to report
     * (on edit, approved and pending payments may already cover the new total).
     */
    protected function validatePaymentProof(Validator $validator, ?Sale $sale = null): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('payment_status') !== 'Paid') {
            return;
        }

        if ($sale) {
            $newTotal = collect((array) $this->input('items', []))
                ->sum(fn ($line) => round((float) ($line['quantity_sold'] ?? 0) * (float) ($line['unit_price'] ?? 0), 2));
            $covered = (float) $sale->payments()->sum('amount') + $sale->pending_approval_amount;

            if ($newTotal <= $covered + 0.009) {
                return;
            }
        }

        $method = SalePayment::methodForChannel($this->input('sales_channel'));

        if (SalePaymentSubmission::proofRequiredFor($method) && empty($this->file('payment_proofs'))) {
            $validator->errors()->add('payment_proofs', 'Upload proof of payment (screenshot, slip or receipt) to mark this sale as paid. It will be sent for approval.');
        }

        if (SalePaymentSubmission::referenceRequiredFor($method) && blank($this->input('payment_reference'))) {
            $validator->errors()->add('payment_reference', 'Enter the '.$method.' transaction reference to mark this sale as paid.');
        }
    }
}
