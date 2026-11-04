<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    // Allow mass assignment for these attributes
    protected $fillable = [
        'name',
        'email',
        'position',
        'phone',
        'province',
        'district',
        'photo',
    ];
}
