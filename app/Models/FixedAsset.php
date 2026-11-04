<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The fixed-asset register. Replaces the workbook line that hard-coded RWF 41,667 a month
 * from a RWF 5,000,000 machine over ten years, and which the two income statements applied
 * inconsistently (one started depreciating in January, the other in February).
 */
class FixedAsset extends Model
{
    use SoftDeletes;

    public const METHOD_STRAIGHT_LINE = 'straight_line';

    protected $fillable = ['name', 'category', 'acquisition_date', 'cost', 'salvage_value', 'useful_life_months', 'method', 'disposal_date', 'disposal_proceeds', 'cash_account_id', 'supplier_id', 'notes', 'created_by'];

    protected $casts = [
        'acquisition_date' => 'date',
        'disposal_date' => 'date',
        'cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'disposal_proceeds' => 'decimal:2',
        'useful_life_months' => 'integer',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Depreciable amount: what is written off over the life, net of any residual value. */
    public function depreciableBase(): float
    {
        return round(max(0, (float) $this->cost - (float) $this->salvage_value), 2);
    }

    /** Straight-line charge for one full month. */
    public function monthlyCharge(): float
    {
        if ($this->useful_life_months < 1) {
            return 0.0;
        }

        return round($this->depreciableBase() / $this->useful_life_months, 2);
    }

    /**
     * Depreciation charged in the calendar month containing $monthStart.
     *
     * Charging begins in the month of acquisition and stops at the earlier of the end of
     * the useful life or the month of disposal. The final month absorbs any rounding
     * remainder so the asset depreciates to exactly its salvage value, never past it.
     */
    public function chargeForMonth(CarbonImmutable $monthStart): float
    {
        $month = $monthStart->startOfMonth();
        $firstMonth = CarbonImmutable::parse($this->acquisition_date)->startOfMonth();

        if ($month->lessThan($firstMonth)) {
            return 0.0;
        }

        if ($this->disposal_date !== null && $month->greaterThan(CarbonImmutable::parse($this->disposal_date)->startOfMonth())) {
            return 0.0;
        }

        $elapsed = ($month->year - $firstMonth->year) * 12 + $month->month - $firstMonth->month;
        if ($elapsed >= $this->useful_life_months) {
            return 0.0;
        }

        $charge = $this->monthlyCharge();

        // Last month of life: true up so the total equals the depreciable base exactly.
        if ($elapsed === $this->useful_life_months - 1) {
            return round($this->depreciableBase() - ($charge * $elapsed), 2);
        }

        return $charge;
    }

    /** Cumulative depreciation from acquisition through the end of the given month. */
    public function accumulatedDepreciationAt(CarbonImmutable $monthEnd): float
    {
        $month = $monthEnd->startOfMonth();
        $cursor = CarbonImmutable::parse($this->acquisition_date)->startOfMonth();
        $total = 0.0;

        while ($cursor->lessThanOrEqualTo($month)) {
            $total = round($total + $this->chargeForMonth($cursor), 2);
            $cursor = $cursor->addMonth();
        }

        return $total;
    }

    /** Carrying amount at a month end, ignoring assets not yet acquired or already disposed. */
    public function netBookValueAt(CarbonImmutable $monthEnd): float
    {
        if (CarbonImmutable::parse($this->acquisition_date)->startOfMonth()->greaterThan($monthEnd->startOfMonth())) {
            return 0.0;
        }

        if ($this->disposal_date !== null && CarbonImmutable::parse($this->disposal_date)->lessThanOrEqualTo($monthEnd)) {
            return 0.0;
        }

        return round((float) $this->cost - $this->accumulatedDepreciationAt($monthEnd), 2);
    }
}
