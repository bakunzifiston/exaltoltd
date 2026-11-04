<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_name',
        'supplier_id',
        'invoice_number',
        'item_type',
        'item_name',
        'lot_number',
        'supplier_lot_number',
        'inspection_status',
        'coa_reference',
        'product_id',
        'quantity_in',
        'quantity_out',
        'damaged',
        'storage_location',
        'record_date',
        'expiry_date',
        'reorder_level',
        'created_by',
        'unit_cost',
        'total_amount',
        'amount_paid',
        'payment_status',
        'payment_due_date',
    ];

    protected $casts = [
        'record_date' => 'date',
        'expiry_date' => 'date',
        'payment_due_date' => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryRecord $record): void {
            if (empty($record->created_by) && auth()->check()) {
                $record->created_by = auth()->id();
            }
        });

        static::saving(function (InventoryRecord $record): void {
            if ($record->supplier_name && ($record->supplier_id === null || $record->isDirty('supplier_name'))) {
                $record->supplier_id = Supplier::resolveByName($record->supplier_name)?->id;
            }
        });

        // Log the opening intake so this delivery appears in its own usage history.
        static::created(function (InventoryRecord $record): void {
            if ((float) $record->quantity_in > 0) {
                InventoryMovement::log(
                    inventoryRecordId: $record->id,
                    type: InventoryMovement::TYPE_INTAKE,
                    quantity: (float) $record->quantity_in,
                    unitCost: $record->unit_cost !== null ? (float) $record->unit_cost : null,
                    referenceType: 'inventory_record',
                    referenceId: $record->id,
                    notes: 'Received from ' . ($record->supplier_name ?? 'supplier'),
                    movedAt: $record->record_date ?? now(),
                );
            }
        });
    }

    public function movements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** This lot has been consumed by production and must be preserved for cost history. */
    public function hasBeenConsumed(): bool
    {
        return (float) $this->quantity_out > 0
            || $this->movements()->where('type', 'consumption')->exists();
    }

    /**
     * Batch codes that drew materials from this delivery.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function batchesUsingThis(): \Illuminate\Support\Collection
    {
        return Production::query()->get(['id', 'batch_id', 'inventory_record_id'])
            ->filter(fn (Production $p) => collect(Production::normalizeMaterials($p->inventory_record_id))
                ->contains(fn ($m) => (int) ($m['inventory_id'] ?? 0) === (int) $this->getKey()))
            ->pluck('batch_id')->unique()->values();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function getIsNearExpiryAttribute(): bool
    {
        return $this->expiry_date !== null
            && ! $this->is_expired
            && $this->expiry_date->lte(now()->addDays(30));
    }

    public const INSPECTION_STATUSES = ['pending' => 'Awaiting inspection', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    /** Complaints, non-conformances and recalls raised against this delivery. */
    public function incidents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TraceIncident::class);
    }

    /**
     * What one unit of this lot cost: the invoice total over the quantity bought, else the
     * keyed-in unit cost. The invoice total comes first because it is what is owed to the
     * supplier, so stock is valued on the same figure as the payable it created.
     */
    public static function costPerUnit(mixed $quantityIn, mixed $unitCost, mixed $totalAmount): ?float
    {
        $quantity = (float) $quantityIn;
        $total = (float) $totalAmount;
        if ($quantity > 0 && $total > 0) {
            return $total / $quantity;
        }

        return (float) $unitCost > 0 ? (float) $unitCost : null;
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) ($this->quantity_in ?? 0) - (float) ($this->quantity_out ?? 0));
    }

    /**
     * Line value for this record (remaining quantity × unit cost).
     */
    public function getLineValueAttribute(): float
    {
        return round($this->remainingQuantity() * (float) ($this->unit_cost ?? 0), 2);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return ($this->total_amount ?? 0) - ($this->amount_paid ?? 0);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'Paid') {
            return false;
        }
        return $this->payment_due_date && $this->payment_due_date->isPast();
    }
}
