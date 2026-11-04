<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The go-live cut-over figures. History stays in the source workbooks; this is the single
 * dated bridge between them and the system, so the two 2025 workbooks that disagree by
 * RWF 8,395,000 never have to be reconciled before reporting can start.
 */
class OpeningBalance extends Model
{
    public const CASH = 'cash';

    public const ACCOUNTS_RECEIVABLE = 'accounts_receivable';

    public const INVENTORY = 'inventory';

    public const FIXED_ASSETS_COST = 'fixed_assets_cost';

    public const ACCUMULATED_DEPRECIATION = 'accumulated_depreciation';

    public const ACCOUNTS_PAYABLE = 'accounts_payable';

    public const LOANS_OUTSTANDING = 'loans_outstanding';

    public const SHARE_CAPITAL = 'share_capital';

    public const RETAINED_EARNINGS = 'retained_earnings';

    /** Lines that add to total assets. Accumulated depreciation is contra: it subtracts. */
    public const ASSET_LINES = [self::CASH, self::ACCOUNTS_RECEIVABLE, self::INVENTORY, self::FIXED_ASSETS_COST];

    public const CONTRA_ASSET_LINES = [self::ACCUMULATED_DEPRECIATION];

    public const LIABILITY_LINES = [self::ACCOUNTS_PAYABLE, self::LOANS_OUTSTANDING];

    public const EQUITY_LINES = [self::SHARE_CAPITAL, self::RETAINED_EARNINGS];

    public const LINES = [
        self::CASH => 'Cash and bank',
        self::ACCOUNTS_RECEIVABLE => 'Accounts receivable',
        self::INVENTORY => 'Inventory',
        self::FIXED_ASSETS_COST => 'Fixed assets at cost',
        self::ACCUMULATED_DEPRECIATION => 'Accumulated depreciation',
        self::ACCOUNTS_PAYABLE => 'Accounts payable',
        self::LOANS_OUTSTANDING => 'Loans outstanding',
        self::SHARE_CAPITAL => 'Share capital',
        self::RETAINED_EARNINGS => 'Retained earnings',
    ];

    protected $fillable = ['as_of_date', 'line_key', 'amount', 'notes', 'created_by'];

    protected $casts = ['as_of_date' => 'date', 'amount' => 'decimal:2'];

    /**
     * Assets less liabilities less equity for a cut-over date. Anything other than zero
     * means the opening position does not balance and no statement built on it will either.
     */
    public static function balanceCheck(string $asOfDate): float
    {
        $rows = static::query()
            ->whereDate('as_of_date', \Carbon\CarbonImmutable::parse($asOfDate)->toDateString())
            ->pluck('amount', 'line_key');

        // An empty set is not a balanced set. Reporting zero here would let a missing
        // cut-over pass silently as though the books balanced.
        if ($rows->isEmpty()) {
            return 0.0;
        }
        $sum = fn (array $keys) => array_sum(array_map(fn ($k) => (float) ($rows[$k] ?? 0), $keys));

        $assets = $sum(self::ASSET_LINES) - $sum(self::CONTRA_ASSET_LINES);

        return round($assets - $sum(self::LIABILITY_LINES) - $sum(self::EQUITY_LINES), 2);
    }
}
