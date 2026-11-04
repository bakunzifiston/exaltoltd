<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalePayment extends Model
{
    public const METHODS = ['Cash', 'MoMo', 'Bank Transfer', 'Card', 'Other'];

    /** How a sale's channel was paid, for payments reported straight from the sale form. */
    public static function methodForChannel(?string $channel): string
    {
        return match ($channel) {
            'Momo Pay' => 'MoMo',
            'Card' => 'Card',
            'Cash' => 'Cash',
            default => 'Other',
        };
    }

    protected $fillable = [
        'sale_id',
        'amount',
        'method',
        'cash_account_id',
        'paid_at',
        'reference',
        'notes',
        'recorded_by',
        'is_backfilled',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'is_backfilled' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Removing a confirmed payment keeps the claim and its proof, marked as reversed.
        static::deleting(function (SalePayment $payment) {
            SalePaymentSubmission::query()
                ->where('sale_payment_id', $payment->id)
                ->update(['status' => SalePaymentSubmission::STATUS_REVERSED]);
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The approved claim this payment came from (none for payments recorded before approvals). */
    public function submission(): HasOne
    {
        return $this->hasOne(SalePaymentSubmission::class, 'sale_payment_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }
}
