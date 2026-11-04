<?php

namespace App\Models;

use App\Models\Concerns\RecordsEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Finished product taken from a batch and given away free: to a supermarket, a customer,
 * a department or an event. It leaves stock at its production cost and is never a sale.
 */
class ProductionSample extends Model
{
    use RecordsEvents;

    public const PENDING = 'pending_approval';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING => 'Pending approval',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    public const RECIPIENT_TYPES = [
        'supermarket' => 'Supermarket / shop',
        'customer' => 'Customer',
        'department' => 'Internal department',
        'event' => 'Event / promotion',
        'other' => 'Other',
    ];

    protected $fillable = [
        'product_id', 'production_id', 'quantity', 'sample_date', 'recipient_type', 'customer_id', 'recipient_name',
        'purpose', 'requested_by_name', 'received_by_name', 'notes', 'created_by',
    ];

    protected $casts = [
        'sample_date' => 'date', 'approved_at' => 'datetime', 'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:4', 'cost_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (ProductionSample $sample): void {
            $sample->forceFill(['reference' => 'SMP-'.$sample->created_at->format('Y').'-'.str_pad((string) $sample->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    protected function untrackedAttributes(): array
    {
        return ['reference', 'unit_cost', 'cost_value', 'approved_by', 'approved_at', 'decision_notes'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function recipientTypeLabel(): string
    {
        return self::RECIPIENT_TYPES[$this->recipient_type] ?? $this->recipient_type;
    }
}
