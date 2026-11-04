<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_WRITTEN_OFF = 'written_off';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_SETTLED => 'Settled',
        self::STATUS_WRITTEN_OFF => 'Written off',
    ];

    protected $fillable = ['lender', 'reference', 'principal', 'interest_rate', 'rate_basis', 'start_date', 'term_months', 'repayment_frequency', 'status', 'cash_account_id', 'notes'];

    protected $casts = [
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'start_date' => 'date',
        'term_months' => 'integer',
    ];

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /**
     * Principal still owed at a date: the amount drawn less principal repaid.
     * Interest is an expense, never part of the outstanding balance.
     */
    public function outstandingAt(CarbonImmutable $asOf): float
    {
        if (CarbonImmutable::parse($this->start_date)->greaterThan($asOf)) {
            return 0.0;
        }

        $repaid = (float) $this->repayments()
            ->whereDate('paid_at', '<=', $asOf->toDateString())
            ->sum('principal_portion');

        return round(max(0, (float) $this->principal - $repaid), 2);
    }

    /**
     * The portion of the outstanding balance falling due within twelve months of $asOf,
     * which is what separates current from non-current liabilities on the balance sheet.
     * Without a full amortisation schedule this is a straight-line approximation.
     */
    public function currentPortionAt(CarbonImmutable $asOf): float
    {
        $outstanding = $this->outstandingAt($asOf);
        if ($outstanding <= 0 || $this->term_months < 1) {
            return 0.0;
        }

        $maturity = CarbonImmutable::parse($this->start_date)->addMonths($this->term_months);
        $monthsRemaining = max(0, ($maturity->year - $asOf->year) * 12 + $maturity->month - $asOf->month);

        if ($monthsRemaining <= 12) {
            return $outstanding;
        }

        return round($outstanding * (12 / $monthsRemaining), 2);
    }

    public function nonCurrentPortionAt(CarbonImmutable $asOf): float
    {
        return round($this->outstandingAt($asOf) - $this->currentPortionAt($asOf), 2);
    }
}
