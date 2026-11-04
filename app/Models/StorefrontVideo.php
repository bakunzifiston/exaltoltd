<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontVideo extends Model
{
    protected $fillable = [
        'title',
        'video_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
