<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A complaint, non-conformance or recall raised against a batch or a raw-material lot. */
class TraceIncident extends Model
{
    public const TYPES = [
        'complaint' => 'Customer complaint',
        'nonconformance' => 'Non-conformance',
        'recall' => 'Recall',
    ];

    public const STATUSES = ['open' => 'Open', 'closed' => 'Closed'];

    protected $fillable = ['type', 'production_id', 'inventory_record_id', 'title', 'description', 'actions_taken', 'disposition', 'status', 'opened_at', 'closed_at', 'created_by'];

    protected $casts = ['opened_at' => 'date', 'closed_at' => 'date'];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function inventoryRecord(): BelongsTo
    {
        return $this->belongsTo(InventoryRecord::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
