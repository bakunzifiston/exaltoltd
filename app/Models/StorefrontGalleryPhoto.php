<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontGalleryPhoto extends Model
{
    protected $fillable = [
        'album_id',
        'title',
        'image_path',
        'is_active',
        'sort_order',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(StorefrontGalleryAlbum::class, 'album_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'alt',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = ltrim((string) $this->image_path, '/');

            if (str_starts_with($path, 'images/')) {
                return asset($path);
            }

            return asset('storage/' . $path);
        });
    }

    protected function alt(): Attribute
    {
        return Attribute::get(function (): string {
            $title = trim((string) $this->title);

            return $title !== '' ? $title : 'Exalto Green Ltd gallery photo';
        });
    }
}
