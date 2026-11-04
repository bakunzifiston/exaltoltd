<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'district',
        'province',
        'source',
        'notes',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Find a customer matching the given identity or create one.
     * Matching is tolerant: same phone wins, otherwise same normalized name.
     */
    public static function resolve(string $name, ?string $phone = null, ?string $email = null, ?string $address = null, string $source = 'admin'): ?self
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $phone = trim((string) $phone) ?: null;
        $digits = $phone ? preg_replace('/\D+/', '', $phone) : null;

        $query = static::query();
        if ($digits) {
            $existing = (clone $query)->whereNotNull('phone')->get()
                ->first(fn (self $c) => preg_replace('/\D+/', '', (string) $c->phone) === $digits);
        } else {
            $existing = (clone $query)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        }

        if ($existing) {
            // Enrich the profile with anything new we learned.
            $existing->fill(array_filter([
                'email' => $existing->email ?: $email,
                'address' => $existing->address ?: $address,
                'phone' => $existing->phone ?: $phone,
            ]));
            if ($existing->isDirty()) {
                $existing->save();
            }
            return $existing;
        }

        return static::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email ?: null,
            'address' => $address ?: null,
            'source' => $source,
        ]);
    }
}
