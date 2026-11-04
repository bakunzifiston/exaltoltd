<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_category_id',
        'name',
        'amount',
        'currency',
        'expense_date',
        'paid_at',
        'payment_status',
        'cash_account_id',
        'month',
        'year',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'paid_at' => 'date',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /** Expenses actually settled in cash within a period, for the cash flow statement. */
    public function scopePaidBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth(Builder $query, int $month): Builder
    {
        return $query->where('month', $month);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->whereHas('category', fn (Builder $inner) => $inner->where('type', $type));
    }
}
