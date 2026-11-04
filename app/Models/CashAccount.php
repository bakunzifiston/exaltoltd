<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use SoftDeletes;

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK = 'bank';

    public const TYPE_MOBILE_MONEY = 'mobile_money';

    public const TYPES = [
        self::TYPE_CASH => 'Cash on hand',
        self::TYPE_BANK => 'Bank account',
        self::TYPE_MOBILE_MONEY => 'Mobile money',
    ];

    protected $fillable = ['name', 'type', 'account_number', 'currency', 'opening_balance', 'opening_balance_date', 'is_active', 'notes'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
