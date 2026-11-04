<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One repayment, split into principal and interest.
 *
 * The split is what lets a single payment feed two statements correctly: interest is an
 * expense on the income statement, principal is a financing outflow on the cash flow
 * statement and a reduction of the liability on the balance sheet. The workbook template
 * listed both under operating expenses, which overstated costs and understated the debt
 * being retired.
 */
class LoanRepayment extends Model
{
    use SoftDeletes;

    protected $fillable = ['loan_id', 'paid_at', 'total_amount', 'principal_portion', 'interest_portion', 'cash_account_id', 'reference', 'recorded_by'];

    protected $casts = [
        'paid_at' => 'date',
        'total_amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /** The two portions must account for the whole payment. */
    public function isSplitConsistent(): bool
    {
        return abs(((float) $this->principal_portion + (float) $this->interest_portion) - (float) $this->total_amount) < 0.01;
    }
}
