<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\AdminDashboardService;
use App\Support\DateBounds;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): View
    {
        $period = (string) $request->query('period', 'all_time');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $analytics = $dashboard->build($period, $startDate, $endDate);

        $salesByProduct = SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select('products.type as product_name', DB::raw('SUM(sale_items.quantity_sold) as total_qty'), DB::raw('SUM(sale_items.line_total) as total_revenue'))
            ->when($analytics['start_date'] && $analytics['end_date'], function ($query) use ($analytics) {
                $query->whereHas('sale', fn ($q) => $q->whereBetween('sale_date', DateBounds::between($analytics['start_date'], $analytics['end_date'])));
            })
            ->groupBy('products.type')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $inventoryValuation = InventoryRecord::query()
            ->select('item_name', DB::raw('SUM(quantity_in - quantity_out) as qty_on_hand'), DB::raw('AVG(unit_cost) as avg_cost'))
            ->groupBy('item_name')
            ->orderByDesc('qty_on_hand')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'item_name' => $row->item_name,
                'qty_on_hand' => (float) $row->qty_on_hand,
                'avg_cost' => (float) $row->avg_cost,
                'value' => round(((float) $row->qty_on_hand) * ((float) $row->avg_cost), 2),
            ]);

        return view('admin.reports.index', array_merge($analytics, [
            'sales_by_product' => $salesByProduct,
            'inventory_valuation' => $inventoryValuation,
            'ar_aging' => $this->receivablesAging(),
            'ap_aging' => $this->payablesAging(),
            'stats' => [
                'total_revenue' => $analytics['revenue_recognised'],
                'gross_profit' => $analytics['gross_profit'],
                'expenses_total' => $analytics['operating_expenses'],
                'net_profit' => $analytics['net_profit'],
                'cash_collected' => $analytics['cash_collected'],
                'fifo_stock_value' => $analytics['fifo_stock_value'],
                'purchases_total' => $analytics['purchases_total'],
            ],
        ]));
    }

    /**
     * Outstanding customer balances bucketed by days overdue
     * (falls back to sale_date when no due_date was set).
     */
    private function receivablesAging(): array
    {
        $buckets = $this->emptyBuckets();

        Sale::query()
            ->where('payment_status', '!=', 'Paid')
            ->whereRaw('total_revenue - returned_amount - amount_paid > 0')
            ->get(['id', 'total_revenue', 'returned_amount', 'amount_paid', 'due_date', 'sale_date'])
            ->each(function (Sale $sale) use (&$buckets): void {
                $balance = $sale->balance_due;
                $reference = $sale->due_date ?? Carbon::parse($sale->sale_date);
                $this->addToBucket($buckets, $reference, $balance, $sale->due_date !== null);
            });

        return $buckets;
    }

    /**
     * Outstanding supplier balances bucketed by days overdue.
     */
    private function payablesAging(): array
    {
        $buckets = $this->emptyBuckets();

        InventoryRecord::query()
            ->whereIn('payment_status', ['Unpaid', 'Partial'])
            ->whereRaw('COALESCE(total_amount, 0) - COALESCE(amount_paid, 0) > 0')
            ->get(['id', 'total_amount', 'amount_paid', 'payment_due_date', 'record_date'])
            ->each(function (InventoryRecord $record) use (&$buckets): void {
                $balance = (float) ($record->total_amount ?? 0) - (float) ($record->amount_paid ?? 0);
                $reference = $record->payment_due_date ?? $record->record_date;
                $this->addToBucket($buckets, $reference, $balance, $record->payment_due_date !== null);
            });

        return $buckets;
    }

    private function emptyBuckets(): array
    {
        return [
            'current' => ['label' => 'Not yet due', 'count' => 0, 'amount' => 0.0],
            '0_30' => ['label' => '1–30 days overdue', 'count' => 0, 'amount' => 0.0],
            '31_60' => ['label' => '31–60 days overdue', 'count' => 0, 'amount' => 0.0],
            '61_90' => ['label' => '61–90 days overdue', 'count' => 0, 'amount' => 0.0],
            '90_plus' => ['label' => 'Over 90 days overdue', 'count' => 0, 'amount' => 0.0],
        ];
    }

    private function addToBucket(array &$buckets, Carbon $reference, float $balance, bool $hasDueDate): void
    {
        $daysOverdue = $reference->isFuture() ? -1 : (int) $reference->diffInDays(now());

        // Without a due date, an old sale date alone shouldn't mark a credit sale "current".
        $key = match (true) {
            $daysOverdue < 0 && $hasDueDate => 'current',
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };

        if ($daysOverdue < 0 && ! $hasDueDate) {
            $key = 'current';
        }

        $buckets[$key]['count']++;
        $buckets[$key]['amount'] = round($buckets[$key]['amount'] + $balance, 2);
    }
}
