<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use SoftDeletes;

    public const TYPE_COGS = 'cogs';

    public const TYPE_OPERATING = 'operating';

    public const SECTION_COSTS = 'costs';

    public const SECTION_OPERATING = 'operating';

    public const SECTION_OTHER = 'other';

    public const SECTION_FINANCING = 'financing';

    /**
     * Depreciation is computed from the fixed-asset register, not from keyed-in expenses.
     * Categories marked this way are excluded from the statement so the same charge is not
     * counted twice, which would also break the balance sheet.
     */
    public const SECTION_DEPRECIATION = 'depreciation';

    /**
     * Loan interest is taken from the repayment register, where it is already split from
     * principal. A keyed-in interest expense would be the same charge counted twice.
     */
    public const SECTION_INTEREST = 'interest';

    /** Sections computed from a register rather than from keyed-in expenses. */
    public const DERIVED_SECTIONS = [self::SECTION_DEPRECIATION, self::SECTION_INTEREST];

    public const SECTIONS = [
        self::SECTION_COSTS => 'Raw materials and direct costs',
        self::SECTION_OPERATING => 'Operating expenses',
        self::SECTION_OTHER => 'Other expenses and tax',
        self::SECTION_FINANCING => 'Loan principal and reserves',
        self::SECTION_DEPRECIATION => 'Depreciation (from asset register)',
        self::SECTION_INTEREST => 'Loan interest (from repayment register)',
    ];

    protected $fillable = [
        'name',
        'type',
        'statement_section',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Which income-statement section this category belongs to. Falls back to the legacy
     * two-value `type` so rows created before the section column still classify correctly.
     */
    public function statementSection(): string
    {
        if ($this->statement_section && array_key_exists($this->statement_section, self::SECTIONS)) {
            return $this->statement_section;
        }

        return $this->type === self::TYPE_COGS ? self::SECTION_COSTS : self::SECTION_OPERATING;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_COGS => 'COGS',
            self::TYPE_OPERATING => 'Operating',
            default => ucfirst((string) $this->type),
        };
    }
}
