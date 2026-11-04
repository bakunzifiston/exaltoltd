<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReport extends Model
{
    use HasFactory;

    public const KIND_INCOME_STATEMENT = 'income_statement';

    public const KIND_FINANCIAL_TABLE = 'financial_table';

    public const KIND_BALANCE_SHEET = 'balance_sheet';

    public const KIND_CASH_FLOW = 'cash_flow';

    public const KINDS = [
        self::KIND_INCOME_STATEMENT => 'Income statement',
        self::KIND_FINANCIAL_TABLE => 'Financial table (RWAZK)',
        self::KIND_BALANCE_SHEET => 'Balance sheet',
        self::KIND_CASH_FLOW => 'Cash flow statement',
    ];

    public const MODE_ACTUAL = 'actual';

    public const MODE_FORECAST = 'forecast';

    public const MODES = [self::MODE_ACTUAL => 'Actual', self::MODE_FORECAST => 'Forecast'];

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [self::SOURCE_SYSTEM => 'System snapshot', self::SOURCE_MANUAL => 'Blank template'];

    /** Kinds with a working calculation engine. */
    public const AVAILABLE_KINDS = [self::KIND_INCOME_STATEMENT, self::KIND_FINANCIAL_TABLE, self::KIND_BALANCE_SHEET, self::KIND_CASH_FLOW];

    /** Kind => label, restricted to what can actually be produced today. */
    public static function selectableKinds(): array
    {
        return array_intersect_key(self::KINDS, array_flip(self::AVAILABLE_KINDS));
    }

    /** Statements whose figures are point-in-time balances rather than flows over the period. */
    public const BALANCE_KINDS = [self::KIND_BALANCE_SHEET];

    protected $fillable = ['title', 'kind', 'mode', 'source', 'start_date', 'as_of', 'prepared_by', 'notes', 'rows', 'source_notes', 'created_by'];

    protected $casts = ['start_date' => 'date', 'as_of' => 'date', 'rows' => 'array', 'source_notes' => 'array', 'version' => 'integer'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The financing section and the three forecast band columns only apply to the RWAZK financial table. */
    public function isFinancialTable(): bool
    {
        return $this->kind === self::KIND_FINANCIAL_TABLE;
    }

    /** Balance sheets carry a closing balance rather than an annual sum. */
    public function isBalanceKind(): bool
    {
        return in_array($this->kind, self::BALANCE_KINDS, true);
    }
}
