<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Production extends Model
{
    use HasFactory;

    public const QC_PENDING = 'pending';

    public const QC_ON_HOLD = 'on_hold';

    public const QC_RELEASED = 'released';

    public const QC_REJECTED = 'rejected';

    public const QC_STATUSES = [
        self::QC_PENDING => 'Awaiting release',
        self::QC_ON_HOLD => 'On hold',
        self::QC_RELEASED => 'Released',
        self::QC_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'batch_id',
        'product_id',
        'inventory_record_id',
        'barcode',
        'quantity_produced',
        'quantity_remaining',
        'damaged',
        'production_date',
        'responsible_staff',
        'employee_id',
        'created_by',
        'quality_control_notes',
        'packaging_date',
        'best_before_date',
        'expiry_date',
        'started_at',
        'finished_at',
        'production_line',
        'shift',
        'recipe_version',
        'storage_location',
    ];

    protected $casts = [
        'inventory_record_id' => 'array',
        'packaging_date' => 'date',
        'best_before_date' => 'date',
        'expiry_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'production_id');
    }

    public function qualityChecks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BatchQualityCheck::class)->latest('checked_at');
    }

    public function incidents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TraceIncident::class)->latest('opened_at');
    }

    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function releasedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Batches that may be sold: released, and not past a use-by date. */
    public function scopeSellable($query)
    {
        return $query->where('qc_status', self::QC_RELEASED)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()->toDateString()));
    }

    /** First expired, first out: earliest best-before first, undated batches last. */
    public function scopeFefo($query)
    {
        return $query->orderByRaw('best_before_date IS NULL')->orderBy('best_before_date')
            ->orderBy('production_date')->orderBy('id');
    }

    public function isSellable(): bool
    {
        return $this->qc_status === self::QC_RELEASED && ! $this->isExpired();
    }

    /** Past its use-by date. A passed best-before is a quality warning, not a block. */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function isPastBestBefore(): bool
    {
        return $this->best_before_date !== null && $this->best_before_date->lt(today());
    }

    /** Why this batch cannot be sold, or null when it can. */
    public function unsellableReason(): ?string
    {
        return match (true) {
            $this->isExpired() => 'expired on '.$this->expiry_date->format('d M Y'),
            $this->qc_status !== self::QC_RELEASED => strtolower(self::QC_STATUSES[$this->qc_status] ?? $this->qc_status),
            default => null,
        };
    }

    protected static function booted(): void
    {
        // Keep the denormalized responsible_staff name in step with the linked employee
        // so existing list/detail views keep showing a readable name.
        static::saving(function (Production $production): void {
            if ($production->employee_id && $production->isDirty('employee_id')) {
                $employee = Employee::find($production->employee_id);
                if ($employee) {
                    $production->responsible_staff = $employee->name;
                }
            }
        });

        static::creating(function (Production $production): void {
            if (empty($production->batch_id)) {
                // Highest existing BATCH-#### number; parse digits only (the hyphen must
                // not be read as a minus sign, which previously produced duplicate IDs).
                $highest = self::query()
                    ->where('batch_id', 'like', 'BATCH-%')
                    ->pluck('batch_id')
                    ->map(fn ($id) => (int) preg_replace('/\D+/', '', (string) $id))
                    ->max() ?? 0;

                $production->batch_id = 'BATCH-' . str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
            }

            // New batches start fully unsold; quantity_produced stays immutable afterwards.
            if ($production->quantity_remaining === null || (float) $production->quantity_remaining <= 0) {
                $production->quantity_remaining = $production->quantity_produced;
            }

            if (empty($production->created_by) && auth()->check()) {
                $production->created_by = auth()->id();
            }

            // A product that needs a quality release starts on hold until someone releases
            // it; anything else is released as made, as every batch was before tracking.
            if (! $production->isDirty('qc_status')) {
                $requiresRelease = (bool) Product::query()->whereKey($production->product_id)->value('requires_qc_release');
                $production->qc_status = $requiresRelease ? self::QC_PENDING : self::QC_RELEASED;
            }

            // Best before from the product's shelf life, unless it was entered.
            if (empty($production->best_before_date) && $production->production_date) {
                $days = Product::query()->whereKey($production->product_id)->value('shelf_life_days');
                if ($days) {
                    $production->best_before_date = \Carbon\Carbon::parse($production->production_date)->addDays((int) $days);
                }
            }
        });

        static::created(function (Production $production): void {
            DB::transaction(function () use ($production): void {
                self::applyMaterials(self::normalizeMaterials($production->inventory_record_id), $production);
            });

            StockMovement::record(
                type: 'produced',
                quantity: (float) $production->quantity_produced,
                productId: $production->product_id,
                productionId: $production->id,
                referenceType: 'production',
                referenceId: $production->id,
            );
        });

        static::updating(function (Production $production): void {
            // Correcting a batch size shifts the unsold remainder by the same delta.
            if ($production->isDirty('quantity_produced')) {
                $delta = (float) $production->quantity_produced - (float) $production->getOriginal('quantity_produced');
                $production->quantity_remaining = max(0, (float) $production->quantity_remaining + $delta);
            }
        });

        static::updated(function (Production $production): void {
            if ($production->wasChanged('quantity_produced')) {
                $delta = (float) $production->quantity_produced - (float) $production->getOriginal('quantity_produced');
                if (abs($delta) > 0.001) {
                    StockMovement::record(
                        type: 'adjustment',
                        quantity: $delta,
                        productId: $production->product_id,
                        productionId: $production->id,
                        referenceType: 'production',
                        referenceId: $production->id,
                        notes: 'Batch size corrected',
                    );
                }
            }

            if (! $production->wasChanged('inventory_record_id')) {
                return;
            }

            DB::transaction(function () use ($production): void {
                $oldMaterials = self::normalizeMaterials($production->getOriginal('inventory_record_id'));
                $newMaterials = self::normalizeMaterials($production->inventory_record_id);

                self::releaseMaterials($oldMaterials, $production);

                try {
                    self::applyMaterials($newMaterials, $production);
                } catch (Exception $exception) {
                    self::applyMaterials($oldMaterials, $production);
                    throw $exception;
                }
            });
        });

        static::deleting(function (Production $production): void {
            self::releaseMaterials(self::normalizeMaterials($production->inventory_record_id), $production);
        });
    }

    /**
     * @return array<int, array{inventory_id: int|string, quantity_used: float|int|string}>
     */
    public static function normalizeMaterials(mixed $materials): array
    {
        if (is_string($materials)) {
            $materials = json_decode($materials, true);
        }

        return is_array($materials) ? $materials : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     */
    public static function applyMaterials(array $materials, ?Production $production = null): void
    {
        foreach ($materials as $item) {
            $inventoryId = $item['inventory_id'] ?? null;
            $usedQty = (float) ($item['quantity_used'] ?? 0);

            if (! $inventoryId || $usedQty <= 0) {
                continue;
            }

            $inventory = InventoryRecord::query()->lockForUpdate()->find($inventoryId);

            if (! $inventory) {
                continue;
            }

            if ($usedQty > $inventory->remainingQuantity() + 0.0001) {
                throw new \App\Exceptions\InsufficientStockException(
                    $inventory->item_name,
                    $usedQty,
                    $inventory->remainingQuantity(),
                );
            }

            $inventory->quantity_out = round((float) $inventory->quantity_out + $usedQty, 2);
            $inventory->save();

            if ($production) {
                InventoryMovement::log(
                    inventoryRecordId: (int) $inventory->id,
                    type: InventoryMovement::TYPE_CONSUMPTION,
                    quantity: -1 * $usedQty,
                    unitCost: $inventory->unit_cost !== null ? (float) $inventory->unit_cost : null,
                    referenceType: 'production',
                    referenceId: (int) $production->id,
                    notes: 'Used in batch ' . $production->batch_id,
                    movedAt: $production->production_date ?? now(),
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     */
    public static function releaseMaterials(array $materials, ?Production $production = null): void
    {
        foreach ($materials as $item) {
            $inventoryId = $item['inventory_id'] ?? null;
            $usedQty = (float) ($item['quantity_used'] ?? 0);

            if (! $inventoryId || $usedQty <= 0) {
                continue;
            }

            $inventory = InventoryRecord::query()->lockForUpdate()->find($inventoryId);

            if (! $inventory) {
                continue;
            }

            $inventory->quantity_out = max(0, round((float) $inventory->quantity_out - $usedQty, 2));
            $inventory->save();

            if ($production) {
                InventoryMovement::log(
                    inventoryRecordId: (int) $inventory->id,
                    type: InventoryMovement::TYPE_REVERSAL,
                    quantity: $usedQty,
                    unitCost: $inventory->unit_cost !== null ? (float) $inventory->unit_cost : null,
                    referenceType: 'production',
                    referenceId: (int) $production->id,
                    notes: 'Returned from batch ' . $production->batch_id . ' (edited/removed)',
                    movedAt: now(),
                );
            }
        }
    }

    /**
     * Raw-material consumption movements recorded against this batch.
     */
    public function inventoryMovements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'reference_id')
            ->where('reference_type', 'production');
    }
}
