<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cash paid to suppliers. The counterpart to SalePayment, and the reason a direct-method
 * cash flow statement is possible: before this, only the running `amount_paid` total on
 * inventory_records existed, which recorded the balance but never the payment event.
 */
class SupplierPayment extends Model
{
    use SoftDeletes;

    public const METHODS = ['Cash', 'MoMo', 'Bank Transfer', 'Card', 'Other'];

    protected $fillable = ['inventory_record_id', 'supplier_id', 'supplier_name', 'amount', 'method', 'paid_at', 'cash_account_id', 'reference', 'notes', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'date'];

    public function inventoryRecord(): BelongsTo
    {
        return $this->belongsTo(InventoryRecord::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
