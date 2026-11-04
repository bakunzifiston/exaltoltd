<?php

namespace App\Models;

use App\Models\Concerns\RecordsEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Products a customer sends back against an earlier delivery.
 *
 * Draft -> pending inspection -> approved (stock and credit posted) or rejected. Nothing
 * touches stock or the customer's balance until a return is approved after inspection.
 */
class StockReturn extends Model
{
    use RecordsEvents;

    public const DRAFT = 'draft';

    public const PENDING_INSPECTION = 'pending_inspection';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::PENDING_INSPECTION => 'Pending inspection',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    /** Statuses that hold quantities against the original delivery. */
    public const OPEN_OR_POSTED = [self::DRAFT, self::PENDING_INSPECTION, self::APPROVED];

    public const REASONS = [
        'unsold' => 'Unsold stock',
        'shelf_too_long' => 'Stayed in the shop too long',
        'payment' => 'Payment-related return',
        'expired' => 'Expired products',
        'damaged' => 'Damaged products',
        'other' => 'Other',
    ];

    protected $fillable = ['sale_id', 'customer_id', 'customer_name', 'delivery_note', 'return_date', 'reason', 'reason_other', 'notes', 'created_by'];

    protected $casts = ['return_date' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'total_value' => 'decimal:2'];

    protected static function booted(): void
    {
        // The reference is built from the id, so it is unique without any counter to race on.
        static::created(function (StockReturn $return): void {
            $return->forceFill(['reference' => 'SR-'.$return->created_at->format('Y').'-'.str_pad((string) $return->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    protected function untrackedAttributes(): array
    {
        return ['reference', 'total_value', 'submitted_by', 'submitted_at', 'decided_by', 'decided_at', 'inspection_notes'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReturnItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function reasonLabel(): string
    {
        return $this->reason === 'other' && $this->reason_other
            ? 'Other: '.$this->reason_other
            : (self::REASONS[$this->reason] ?? $this->reason);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
