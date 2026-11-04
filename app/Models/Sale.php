<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_id',
        'customer_name',
        'customer_Phone',
        'customer_id',
        'customer_email',
        'delivery_address',
        'product_id',
        'production_id',
        'quantity_sold',
        'selling_price',
        'total_revenue',
        'amount_paid',
        'payment_status',
        'paid_at',
        'delivery_status',
        'delivered_at',
        'sales_channel',
        'created_by',
        'invoice_number',
        'sale_date',
        'due_date',
        'barcode',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'total_revenue' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (empty($sale->sales_id)) {
                $sale->sales_id = 'SALE-' . now()->format('YmdHis') . '-' . rand(100, 999);
            }

            // Stamp the staff member who recorded this sale (null for online checkout).
            if (empty($sale->created_by) && auth()->check()) {
                $sale->created_by = auth()->id();
            }
        });

        static::created(function (Sale $sale) {
            foreach (['payment_status', 'delivery_status'] as $field) {
                if ($sale->{$field}) {
                    $sale->statusEvents()->create([
                        'field' => $field,
                        'from_status' => null,
                        'to_status' => $sale->{$field},
                        'changed_by' => auth()->id(),
                    ]);
                }
            }
        });

        static::updating(function (Sale $sale) {
            if ($sale->isDirty('delivery_status') && $sale->delivery_status === 'Delivered' && !$sale->delivered_at) {
                $sale->delivered_at = now();
            }
            if ($sale->isDirty('payment_status') && $sale->payment_status === 'Paid' && !$sale->paid_at) {
                $sale->paid_at = now();
            }
        });

        static::updated(function (Sale $sale) {
            foreach (['payment_status', 'delivery_status'] as $field) {
                if ($sale->wasChanged($field)) {
                    $sale->statusEvents()->create([
                        'field' => $field,
                        'from_status' => $sale->getOriginal($field),
                        'to_status' => $sale->{$field},
                        'changed_by' => auth()->id(),
                    ]);
                }
            }
        });

        // Proof files live on disk; the DB cascade removes their rows without model events.
        static::deleting(function (Sale $sale) {
            SalePaymentProof::query()
                ->whereIn('submission_id', $sale->paymentSubmissions()->select('id'))
                ->get()
                ->each->delete();
        });

        // Stock reduction is done only in SaleItem::created when items exist.
    }

    /**
     * Re-derive amount_paid / payment_status / paid_at from the payments ledger.
     */
    public function recalculatePaymentState(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        // What the customer owes for: the invoice less goods they returned.
        $total = $this->net_total;

        $this->amount_paid = $paid;

        if ($total > 0 && $paid + 0.009 >= $total) {
            $this->payment_status = 'Paid';
            $latest = $this->payments()->max('paid_at');
            $this->paid_at = $latest ? \Illuminate\Support\Carbon::parse($latest) : now();
        } elseif ($paid > 0) {
            $this->payment_status = 'Partially Paid';
            $this->paid_at = null;
        } else {
            if (!in_array($this->payment_status, ['Pending', 'Credit'], true)) {
                $this->payment_status = 'Pending';
            }
            $this->paid_at = null;
        }

        $this->save();
    }

    /** The invoice less approved returns: what the customer is actually charged. */
    public function getNetTotalAttribute(): float
    {
        return round((float) $this->total_revenue - (float) $this->returned_amount, 2);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, round($this->net_total - (float) $this->amount_paid, 2));
    }

    /** Paid more than the goods kept are worth, after a return: a refund or credit is owed. */
    public function getCreditDueAttribute(): float
    {
        return max(0, round((float) $this->amount_paid - $this->net_total, 2));
    }

    /** Reported payments still waiting for an approver. */
    public function getPendingApprovalAmountAttribute(): float
    {
        return round((float) $this->pendingSubmissions()->sum('amount'), 2);
    }

    /**
     * What may still be reported as paid: the invoice less confirmed payments and less
     * claims already waiting for approval, so two claims can't together exceed the invoice.
     */
    public function getSubmittableBalanceAttribute(): float
    {
        $approved = (float) $this->payments()->sum('amount');

        return max(0, round($this->net_total - $approved - $this->pending_approval_amount, 2));
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'Paid') {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isPast();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(SalePaymentSubmission::class);
    }

    public function pendingSubmissions(): HasMany
    {
        return $this->hasMany(SalePaymentSubmission::class)->where('status', SalePaymentSubmission::STATUS_PENDING);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(SaleStatusEvent::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
