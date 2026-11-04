<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontGalleryAlbum extends Model
{
    protected $fillable = [
        'title',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(StorefrontGalleryPhoto::class, 'album_id');
    }

    public function activePhotos(): HasMany
    {
        return $this->photos()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function coverPhoto(): ?StorefrontGalleryPhoto
    {
        if ($this->relationLoaded('photos')) {
            return $this->photos->first();
        }

        return $this->activePhotos()->first();
    }

    public function coverImageUrl(): ?string
    {
        return $this->coverPhoto()?->image_url;
    }
}
