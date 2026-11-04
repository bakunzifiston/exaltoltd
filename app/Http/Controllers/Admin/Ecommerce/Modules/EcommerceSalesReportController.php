<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\DateBounds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EcommerceSalesReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = (string) $request->query('period', '30d');
        [$startDate, $endDate] = $this->resolvePeriod($period);

        $salesBase = Sale::query()
            ->whereBetween('sale_date', DateBounds::between($startDate, $endDate));

        $kpis = (clone $salesBase)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_revenue) as gross_revenue')
            ->selectRaw('AVG(total_revenue) as avg_order_value')
            ->selectRaw('SUM(CASE WHEN payment_status = "Paid" THEN total_revenue ELSE 0 END) as paid_revenue')
            ->selectRaw('SUM(CASE WHEN delivery_status = "Returned" THEN 1 ELSE 0 END) as returned_orders')
            ->first();

        $channelPerformance = (clone $salesBase)
            ->selectRaw("COALESCE(NULLIF(sales_channel, ''), 'Unassigned') as sales_channel")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_revenue) as revenue')
            ->selectRaw('AVG(total_revenue) as avg_order_value')
            ->selectRaw('SUM(CASE WHEN delivery_status = "Returned" THEN 1 ELSE 0 END) as returned_orders')
            ->groupBy('sales_channel')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sale_date', DateBounds::between($startDate, $endDate))
            ->selectRaw('products.type as product_name')
            ->selectRaw('SUM(sale_items.quantity_sold) as units_sold')
            ->selectRaw('SUM(sale_items.line_total) as revenue')
            ->groupBy('products.type')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $paymentMix = (clone $salesBase)
            ->select('payment_status', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_revenue) as revenue'))
            ->groupBy('payment_status')
            ->orderByDesc('orders_count')
            ->get();

        return view('admin.ecommerce.modules.sales-reports', [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'kpis' => $kpis,
            'channelPerformance' => $channelPerformance,
            'topProducts' => $topProducts,
            'paymentMix' => $paymentMix,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(string $period): array
    {
        $end = now()->toDateString();

        $start = match ($period) {
            '7d' => now()->subDays(6)->toDateString(),
            '30d' => now()->subDays(29)->toDateString(),
            '90d' => now()->subDays(89)->toDateString(),
            '365d' => now()->subDays(364)->toDateString(),
            default => now()->subDays(29)->toDateString(),
        };

        return [$start, $end];
    }
}
