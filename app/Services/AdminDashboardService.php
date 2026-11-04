<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Support\DateBounds;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
        private readonly InventoryValuationService $valuation,
        private readonly ProductionCostingService $production,
    ) {}

    public function build(?string $period, ?string $startDate, ?string $endDate): array
    {
        [$start, $end] = $this->resolveDates($period, $startDate, $endDate);

        $valuationService = $this->valuation;

        // ── Base queries ────────────────────────────────────────────────────
        $salesQ = Sale::query()->when($start && $end, fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)));
        $saleItemsQ = SaleItem::query()->when($start && $end, fn ($q) => $q->whereHas('sale', fn ($s) => $s->whereBetween('sale_date', DateBounds::between($start, $end))));
        $productionQ = Production::query()->when($start && $end, fn ($q) => $q->whereBetween('production_date', DateBounds::between($start, $end)));
        $inventoryQ = InventoryRecord::query()->when($start && $end, fn ($q) => $q->whereBetween('record_date', DateBounds::between($start, $end)));
        $expenseOpQ = Expense::query()->whereHas('category', fn ($q) => $q->where('type', ExpenseCategory::TYPE_OPERATING))->when($start && $end, fn ($q) => $q->whereBetween('expense_date', DateBounds::between($start, $end)));
        $paymentsQ = SalePayment::query()->when($start && $end, fn ($q) => $q->whereBetween('paid_at', DateBounds::between($start, $end)));

        // ── Financial ───────────────────────────────────────────────────────
        // Revenue is recognised on delivery, on the one shared rule, so this page and the
        // financial report cannot disagree. Collections are reported separately as cash.
        $revenueAllQ = (clone $salesQ);

        $recognisedRevenue = $this->revenue->recognisedRevenue(
            $start?->toDateString(),
            $end?->toDateString()
        );
        $totalRevenueAll = (float) $revenueAllQ->sum('total_revenue');

        // COGS on the income statement's basis: raw materials consumed in production,
        // adjusted for finished goods still in stock. Raw-material purchases are NOT added:
        // they move value into inventory, and adding them alongside COGS-classified
        // expenses double-counted the same spend.
        $cogs = $this->production->costOfGoodsSold(
            $start?->toDateString(),
            ($end ?? now())->toDateString()
        )['total'];

        $grossProfit = $recognisedRevenue - $cogs;
        $grossMarginPct = $recognisedRevenue > 0 ? round(($grossProfit / $recognisedRevenue) * 100, 1) : 0;
        $operatingExpenses = (float) $expenseOpQ->sum('amount');
        $netProfit = $grossProfit - $operatingExpenses;
        $netMarginPct = $recognisedRevenue > 0 ? round(($netProfit / $recognisedRevenue) * 100, 1) : 0;
        $cashCollected = (float) $paymentsQ->sum('amount');
        $purchasesTotal = (float) (clone $inventoryQ)->sum('total_amount');

        $arBalance = (float) (clone $salesQ)
            ->whereIn('payment_status', ['Pending', 'Credit', 'Partially Paid'])
            ->selectRaw('COALESCE(SUM(total_revenue - returned_amount - amount_paid), 0) as balance')
            ->value('balance');

        $apBalance = (float) InventoryRecord::query()
            ->whereIn('payment_status', ['Unpaid', 'Partial'])
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as balance')
            ->value('balance');

        $overdueAR = (clone $salesQ)
            ->whereIn('payment_status', ['Pending', 'Credit', 'Partially Paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        $overdueAP = InventoryRecord::query()
            ->whereIn('payment_status', ['Unpaid', 'Partial'])
            ->whereNotNull('payment_due_date')
            ->whereDate('payment_due_date', '<', today())
            ->count();

        // ── Sales ───────────────────────────────────────────────────────────
        $totalOrders = (clone $salesQ)->count();
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenueAll / $totalOrders, 0) : 0;
        $totalUnits = (float) $saleItemsQ->sum('quantity_sold');

        $byChannel = (clone $salesQ)
            ->select('sales_channel', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('sales_channel')
            ->get()
            ->map(fn ($r) => ['channel' => $r->sales_channel ?? 'Unknown', 'orders' => (int) $r->orders, 'revenue' => (float) $r->revenue])
            ->values()->all();

        $paymentStatusBreakdown = (clone $salesQ)
            ->select('payment_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_revenue) as total'))
            ->groupBy('payment_status')
            ->get()
            ->map(fn ($r) => ['status' => $r->payment_status, 'count' => (int) $r->count, 'total' => (float) $r->total])
            ->values()->all();

        $pendingDelivery = (clone $salesQ)->where('delivery_status', 'Pending')->count();
        $returnedOrders = (clone $salesQ)->where('delivery_status', 'Returned')->count();

        $topProductsByRevenue = SaleItem::query()
            ->select('product_id', DB::raw('SUM(line_total) as revenue'), DB::raw('SUM(quantity_sold) as units'))
            ->with('product:id,name')
            ->when($start && $end, fn ($q) => $q->whereHas('sale', fn ($s) => $s->whereBetween('sale_date', DateBounds::between($start, $end))))
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->product?->name ?? 'Unknown', 'revenue' => (float) $r->revenue, 'units' => (float) $r->units])
            ->values()->all();

        $topProductsByUnits = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as units'), DB::raw('SUM(line_total) as revenue'))
            ->with('product:id,name')
            ->when($start && $end, fn ($q) => $q->whereHas('sale', fn ($s) => $s->whereBetween('sale_date', DateBounds::between($start, $end))))
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->product?->name ?? 'Unknown', 'units' => (float) $r->units, 'revenue' => (float) $r->revenue])
            ->values()->all();

        // ── Production & Stock ──────────────────────────────────────────────
        $producedUnits = (float) (clone $productionQ)->sum('quantity_produced');
        $damagedUnits = (float) (clone $productionQ)->sum('damaged');

        // Valued at what the stock cost to make (the batch's raw-material cost per unit), the
        // same basis as the balance sheet, not at its selling price.
        $unitCosts = $this->production->unitCostsFor([]);
        $finishedGoodsByProduct = Production::query()
            ->with('product:id,name')
            ->get(['id', 'product_id', 'quantity_remaining', 'quantity_produced'])
            ->groupBy('product_id')
            ->map(fn ($batches) => [
                'name' => $batches->first()->product?->name ?? 'Unknown',
                'remaining' => (float) $batches->sum('quantity_remaining'),
                'produced' => (float) $batches->sum('quantity_produced'),
                'value' => round($batches->sum(fn ($b) => (float) $b->quantity_remaining * ($unitCosts[$b->id] ?? 0)), 0),
            ])
            ->values()->all();

        $finishedGoodsValue = array_sum(array_column($finishedGoodsByProduct, 'value'));

        $productionByProduct = (clone $productionQ)
            ->select('product_id', DB::raw('SUM(quantity_produced) as total'))
            ->with('product:id,name')
            ->groupBy('product_id')
            ->get()
            ->map(fn ($r) => ['label' => $r->product?->name ?? 'Unknown', 'value' => (float) $r->total])
            ->values()->all();

        $rawMaterialStock = (float) InventoryRecord::sum('quantity_in') - (float) InventoryRecord::sum('quantity_out');
        $fifoStockValue = $valuationService->rawStockValuesAt([today()->toDateString()])[today()->toDateString()];
        $lowStockCount = $this->lowStockMaterialsCount();
        $expiringCount = $this->expiringMaterialsCount();

        $damagedRawMaterials = (float) InventoryRecord::sum('damaged');

        // ── Customers ──────────────────────────────────────────────────────
        $totalCustomers = Customer::count();
        $newCustomers = Customer::query()
            ->when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();

        $topCustomers = (clone $salesQ)
            ->select('customer_name', 'customer_id', DB::raw('SUM(total_revenue) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('customer_name', 'customer_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->customer_name, 'revenue' => (float) $r->revenue, 'orders' => (int) $r->orders])
            ->values()->all();

        // Repeat = customer_name appears in more than one sale (all time)
        $repeatRevenue = Sale::query()
            ->select('customer_name', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('customer_name')
            ->having('cnt', '>', 1)
            ->get();
        $repeatCustomerRevenue = (float) $repeatRevenue->sum('revenue');
        $repeatCustomerCount = $repeatRevenue->count();

        // Avg days to payment. DATEDIFF is MySQL-only, so pick the expression per driver
        // rather than letting this query break on SQLite.
        $dayDiff = Sale::query()->getConnection()->getDriverName() === 'sqlite'
            ? 'julianday(paid_at) - julianday(sale_date)'
            : 'DATEDIFF(paid_at, sale_date)';

        $avgDaysToPayment = round((float) Sale::query()
            ->whereNotNull('paid_at')
            ->whereNotNull('sale_date')
            ->selectRaw("AVG($dayDiff) as avg_days")
            ->value('avg_days') ?? 0, 1);

        $recentWebsiteOrders = Sale::query()
            ->where('sales_channel', 'Online Store')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'sales_id', 'customer_name', 'barcode', 'customer_Phone', 'total_revenue', 'payment_status', 'delivery_status', 'sale_date', 'created_at']);

        $pendingWebsiteOrdersCount = Sale::query()
            ->where('sales_channel', 'Online Store')
            ->where('payment_status', 'Pending')
            ->count();

        // ── Suppliers ──────────────────────────────────────────────────────
        $topSuppliers = (clone $inventoryQ)
            ->select('supplier_name', DB::raw('SUM(total_amount) as spend'), DB::raw('COUNT(*) as deliveries'))
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name')
            ->orderByDesc('spend')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->supplier_name, 'spend' => (float) $r->spend, 'deliveries' => (int) $r->deliveries])
            ->values()->all();

        // ── People ──────────────────────────────────────────────────────────
        $headcount = Employee::count();

        $salesByStaff = Sale::query()
            ->select('created_by', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_revenue) as revenue'))
            ->with('creator:id,name')
            ->when($start && $end, fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)))
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->creator?->name ?? 'Unknown', 'orders' => (int) $r->orders, 'revenue' => (float) $r->revenue])
            ->values()->all();

        $productionByEmployee = Production::query()
            ->select('employee_id', DB::raw('SUM(quantity_produced) as units'), DB::raw('COUNT(*) as batches'))
            ->with('employee:id,name')
            ->when($start && $end, fn ($q) => $q->whereBetween('production_date', DateBounds::between($start, $end)))
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->orderByDesc('units')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->employee?->name ?? 'Unknown', 'units' => (float) $r->units, 'batches' => (int) $r->batches])
            ->values()->all();

        $revenuePerEmployee = $headcount > 0 ? round($recognisedRevenue / $headcount, 0) : 0;

        // ── Charts ──────────────────────────────────────────────────────────
        $monthKeys = $this->monthWindow($end);
        $monthlyRevenue = $this->monthlySalesRevenue($start, $end);
        $monthlyProduction = $this->monthlyProduction($start, $end);
        $monthlyExpenses = $this->monthlyExpenses($start, $end);
        $monthlyOrders = $this->monthlyOrderCount($start, $end);

        return [
            'period' => $period ?? 'all_time',
            'start_date' => $start?->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),

            // Financial
            'revenue_recognised' => $recognisedRevenue,
            'total_revenue_all' => $totalRevenueAll,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
            'net_margin_pct' => $netMarginPct,
            'cash_collected' => $cashCollected,
            'purchases_total' => $purchasesTotal,
            'ar_balance' => $arBalance,
            'ap_balance' => $apBalance,
            'overdue_ar_count' => $overdueAR,
            'overdue_ap_count' => $overdueAP,

            // Sales
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'total_units_sold' => $totalUnits,
            'by_channel' => $byChannel,
            'payment_status_breakdown' => $paymentStatusBreakdown,
            'pending_delivery_count' => $pendingDelivery,
            'returned_orders_count' => $returnedOrders,
            'top_products_by_revenue' => $topProductsByRevenue,
            'top_products_by_units' => $topProductsByUnits,

            // Production & stock
            'produced_units' => $producedUnits,
            'damaged_units' => $damagedUnits,
            'finished_goods_by_product' => $finishedGoodsByProduct,
            'finished_goods_value' => $finishedGoodsValue,
            'production_by_product' => $productionByProduct,
            'raw_material_stock' => $rawMaterialStock,
            'fifo_stock_value' => $fifoStockValue,
            'low_stock_count' => $lowStockCount,
            'expiring_count' => $expiringCount,
            'damaged_raw_materials' => $damagedRawMaterials,

            // Customers
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'top_customers' => $topCustomers,
            'repeat_customer_revenue' => $repeatCustomerRevenue,
            'repeat_customer_count' => $repeatCustomerCount,
            'avg_days_to_payment' => $avgDaysToPayment,
            'recent_website_orders' => $recentWebsiteOrders,
            'pending_website_orders_count' => $pendingWebsiteOrdersCount,

            // Suppliers
            'top_suppliers' => $topSuppliers,

            // People
            'headcount' => $headcount,
            'sales_by_staff' => $salesByStaff,
            'production_by_employee' => $productionByEmployee,
            'revenue_per_employee' => $revenuePerEmployee,

            // Charts
            'month_labels' => array_map(fn ($key) => Carbon::createFromFormat('Y-m-d', $key.'-01')->format('M y'), $monthKeys),
            'monthly_revenue' => $monthlyRevenue,
            'monthly_production' => $monthlyProduction,
            'monthly_expenses' => $monthlyExpenses,
            'monthly_orders' => $monthlyOrders,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function lowStockMaterialsCount(): int
    {
        $remaining = 'CASE WHEN quantity_in > quantity_out THEN quantity_in - quantity_out ELSE 0 END';

        return DB::query()->fromSub(
            InventoryRecord::query()
                ->whereNotNull('reorder_level')->where('reorder_level', '>', 0)
                ->groupBy('item_name')
                ->havingRaw("SUM($remaining) <= MAX(reorder_level)")
                ->select('item_name'),
            'low_stock'
        )->count();
    }

    private function expiringMaterialsCount(): int
    {
        return InventoryRecord::query()
            ->where('expiry_date', '>=', today()->toDateString())
            ->where('expiry_date', '<=', DateBounds::endOfDay(today()->addDays(30)))
            ->whereColumn('quantity_in', '>', 'quantity_out')
            ->count();
    }

    private function resolveDates(?string $period, ?string $startDate, ?string $endDate): array
    {
        if ($period && $period !== 'all_time') {
            return match ($period) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
        }
        if ($startDate && $endDate) {
            return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
        }

        return [null, null];
    }

    /**
     * The twelve calendar months the monthly charts cover, as Y-m keys: the year ending
     * with the selected period, or with the current month when no period is selected.
     *
     * @return list<string>
     */
    private function monthWindow(?Carbon $end): array
    {
        $last = ($end ?? now())->copy()->startOfMonth();

        return array_map(fn ($i) => $last->copy()->subMonths(11 - $i)->format('Y-m'), range(0, 11));
    }

    /**
     * Sum a column per month over the chart window and the selected period.
     *
     * @return list<float>
     */
    private function monthlySeries($query, string $dateColumn, string $aggregate, ?Carbon $start, ?Carbon $end): array
    {
        $keys = $this->monthWindow($end);
        $from = Carbon::createFromFormat('Y-m-d', $keys[0].'-01');
        $from = $start && $start->gt($from) ? $start : $from;
        $to = Carbon::createFromFormat('Y-m-d', $keys[11].'-01')->endOfMonth();
        $to = $end && $end->lt($to) ? $end : $to;

        $rows = $query
            ->selectRaw($this->monthExpr($dateColumn).' as month, '.$aggregate.' as total')
            ->whereBetween($dateColumn, DateBounds::between($from, $to))
            ->groupByRaw($this->monthExpr($dateColumn))
            ->pluck('total', 'month');

        return array_map(fn ($key) => (float) ($rows[$key] ?? 0), $keys);
    }

    private function monthlySalesRevenue(?Carbon $start, ?Carbon $end): array
    {
        return $this->monthlySeries(Sale::query(), 'sale_date', 'SUM(total_revenue)', $start, $end);
    }

    private function monthlyProduction(?Carbon $start, ?Carbon $end): array
    {
        return $this->monthlySeries(Production::query(), 'production_date', 'SUM(quantity_produced)', $start, $end);
    }

    /**
     * Operating expenses plus the raw materials production consumed, on the same basis as
     * the Expenses page. Raw-material purchases are stock, not spending, until used.
     */
    private function monthlyExpenses(?Carbon $start, ?Carbon $end): array
    {
        $opex = $this->monthlySeries(
            Expense::query()->whereHas('category', fn ($q) => $q->where('type', ExpenseCategory::TYPE_OPERATING)),
            'expense_date', 'SUM(amount)', $start, $end,
        );

        $keys = $this->monthWindow($end);
        $consumed = array_fill_keys($keys, 0.0);
        foreach ($this->production->consumption($start?->toDateString(), $end?->toDateString()) as $line) {
            $month = substr($line['production_date'], 0, 7);
            if (isset($consumed[$month]) && $line['cost'] !== null) {
                $consumed[$month] += $line['cost'];
            }
        }

        return array_map(fn ($i) => round($opex[$i] + array_values($consumed)[$i], 0), range(0, 11));
    }

    private function monthlyOrderCount(?Carbon $start, ?Carbon $end): array
    {
        return array_map('intval', $this->monthlySeries(Sale::query(), 'sale_date', 'COUNT(*)', $start, $end));
    }

    /** A year-and-month key (Y-m) for a date column, per database driver. */
    private function monthExpr(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', $column)",
            'pgsql' => "to_char($column, 'YYYY-MM')",
            default => "DATE_FORMAT($column, '%Y-%m')",
        };
    }
}
