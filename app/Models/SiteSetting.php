<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, ?string $value, string $group = 'general', string $type = 'text'): void
    {
        static::updateOrCreate(['key' => $key], compact('value', 'group', 'type'));
    }
}
