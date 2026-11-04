<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactChannel extends Model
{
    protected $fillable = ['type', 'label', 'value', 'is_primary', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type): mixed
    {
        return $query->where('type', $type);
    }
}
