<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\StockReturn;
use App\Support\DateBounds;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single definition of recognised revenue for the whole application.
 *
 * Revenue is earned when goods are delivered, whether or not the customer has paid.
 * Collections are cash, not revenue, and are reported separately. Before this class
 * existed the dashboard recognised on `payment_status = Paid` while the financial
 * report recognised on `delivery_status = Delivered`, so the two pages disagreed.
 */
class RevenueRecognitionService
{
    public const DELIVERED = 'Delivered';

    public const BASIS_NOTE = 'Revenue includes delivered sales by sale date, whether paid or on credit. Pending and returned deliveries are excluded. Collections are not revenue.';

    /** Statuses that represent an unsettled customer balance. */
    public const OPEN_RECEIVABLE_STATUSES = ['Pending', 'Credit', 'Partially Paid'];

    /** Sales whose revenue is recognised, optionally bounded by sale date. */
    public function deliveredSales(?string $from = null, ?string $to = null): Builder
    {
        return Sale::query()
            ->where('delivery_status', self::DELIVERED)
            ->when($from !== null, fn ($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('sale_date', '<=', $to));
    }

    /** Total recognised revenue for a period, less goods returned in it. */
    public function recognisedRevenue(?string $from = null, ?string $to = null): float
    {
        return round((float) $this->deliveredSales($from, $to)->sum('total_revenue') - $this->creditNotes($from, $to)->sum('total_value'), 2);
    }

    /**
     * Approved returns against delivered orders: credit notes that reduce revenue on the
     * return date, so earlier periods already reported are never rewritten.
     */
    public function creditNotes(?string $from = null, ?string $to = null): Builder
    {
        return StockReturn::query()
            ->where('status', StockReturn::APPROVED)
            ->whereHas('sale', fn ($q) => $q->where('delivery_status', self::DELIVERED))
            ->when($from !== null, fn ($q) => $q->where('return_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('return_date', '<=', DateBounds::endOfDay($to)));
    }

    /**
     * Outstanding trade receivables as at a date: delivered and undelivered sales alike
     * create a claim once invoiced, so this deliberately spans all sales, not just delivered.
     */
    public function receivablesAsAt(?string $asOf = null): float
    {
        return round((float) Sale::query()
            ->when($asOf !== null, fn ($q) => $q->whereDate('sale_date', '<=', $asOf))
            ->whereIn('payment_status', self::OPEN_RECEIVABLE_STATUSES)
            ->selectRaw('COALESCE(SUM(total_revenue - returned_amount - amount_paid), 0) as balance')
            ->value('balance'), 2);
    }
}
