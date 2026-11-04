<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    // Allow mass assignment for these attributes
    protected $fillable = [
        'name',
        'type',
        'description',
        'barcode',
        'price',
        'compare_at_price',
        'min_order_qty',
        'image_path',
        'sku',
        'shelf_life_days',
        'requires_qc_release',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'min_order_qty' => 'integer',
            'shelf_life_days' => 'integer',
            'requires_qc_release' => 'boolean',
        ];
    }
}

