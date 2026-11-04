<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_INTAKE = 'intake';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSAL = 'reversal';

    protected $fillable = [
        'inventory_record_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'moved_at' => 'datetime',
    ];

    public function inventoryRecord(): BelongsTo
    {
        return $this->belongsTo(InventoryRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The production batch this movement is tied to (only for production-referenced rows).
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'reference_id');
    }

    /**
     * Convenience writer used by the consumption/intake hooks.
     */
    public static function log(
        int $inventoryRecordId,
        string $type,
        float $quantity,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        $movedAt = null
    ): self {
        return static::create([
            'inventory_record_id' => $inventoryRecordId,
            'type' => $type,
            'quantity' => round($quantity, 2),
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => auth()->id(),
            'moved_at' => $movedAt ?? now(),
        ]);
    }
}
