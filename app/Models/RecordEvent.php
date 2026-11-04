<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One entry in the audit trail of a return or sample. Written once, never edited. */
class RecordEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event', 'from_status', 'to_status', 'details', 'user_id', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function description(): string
    {
        return match ($this->event) {
            'created' => 'Created'.($this->to_status ? ' as '.str_replace('_', ' ', $this->to_status) : ''),
            'status_changed' => 'Status changed from '.str_replace('_', ' ', (string) $this->from_status).' to '.str_replace('_', ' ', (string) $this->to_status),
            'edited' => 'Edited: '.implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $this->details['fields'] ?? [])),
            'document_added' => 'Document attached: '.($this->details['name'] ?? ''),
            'document_removed' => 'Document removed: '.($this->details['name'] ?? ''),
            default => ucfirst(str_replace('_', ' ', $this->event)).(isset($this->details['note']) ? ': '.$this->details['note'] : ''),
        };
    }
}
