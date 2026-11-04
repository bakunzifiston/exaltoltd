<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailRoutingRule extends Model
{
    protected $fillable = ['event', 'recipient_email', 'recipient_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event): mixed
    {
        return $query->where('event', $event);
    }

    public static function recipientsFor(string $event): array
    {
        return static::active()->forEvent($event)->get(['recipient_email', 'recipient_name'])->all();
    }
}
