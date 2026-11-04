<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquityMovement extends Model
{
    use SoftDeletes;

    public const TYPE_SHARE_CAPITAL = 'share_capital';

    public const TYPE_CONTRIBUTION = 'contribution';

    public const TYPE_DRAWING = 'drawing';

    public const TYPES = [
        self::TYPE_SHARE_CAPITAL => 'Share capital',
        self::TYPE_CONTRIBUTION => 'Owner contribution',
        self::TYPE_DRAWING => 'Owner drawing',
    ];

    /** Drawings reduce equity and cash; the other two increase both. */
    public const OUTFLOW_TYPES = [self::TYPE_DRAWING];

    protected $fillable = ['type', 'amount', 'occurred_at', 'cash_account_id', 'description', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'occurred_at' => 'date'];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /** Signed effect on equity and on cash. */
    public function signedAmount(): float
    {
        $sign = in_array($this->type, self::OUTFLOW_TYPES, true) ? -1 : 1;

        return round($sign * (float) $this->amount, 2);
    }
}
