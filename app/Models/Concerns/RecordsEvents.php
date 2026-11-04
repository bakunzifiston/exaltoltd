<?php

namespace App\Models\Concerns;

use App\Models\RecordEvent;
use App\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An audit trail and attached documents for a transaction record.
 *
 * Creation, every status change and every edit of a tracked field is written to
 * record_events with who did it, so the history of a return or sample can be read back.
 */
trait RecordsEvents
{
    public static function bootRecordsEvents(): void
    {
        static::created(fn ($model) => $model->logEvent('created', null, $model->status));

        static::updated(function ($model): void {
            if ($model->wasChanged('status')) {
                $model->logEvent('status_changed', $model->getOriginal('status'), $model->status);
            }

            $edited = array_diff(array_keys($model->getChanges()), ['status', 'updated_at', ...$model->untrackedAttributes()]);
            if ($edited !== []) {
                $model->logEvent('edited', null, null, ['fields' => array_values($edited)]);
            }
        });
    }

    /** Attributes changed by the workflow itself, which would only repeat the status event. */
    protected function untrackedAttributes(): array
    {
        return [];
    }

    public function events(): MorphMany
    {
        return $this->morphMany(RecordEvent::class, 'recordable')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(TransactionDocument::class, 'documentable')->latest('id');
    }

    public function logEvent(string $event, ?string $from = null, ?string $to = null, ?array $details = null): void
    {
        $this->events()->create([
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'details' => $details,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
