<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $focus = (string) $request->query('focus', 'all');
        $sort = (string) $request->query('sort', 'orders_count');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sales_channel', 'orders_count', 'avg_order_value', 'gross_revenue', 'credit_orders'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'orders_count';
        }

        $segments = Sale::query()
            ->selectRaw("COALESCE(NULLIF(sales_channel, ''), 'Unassigned') as sales_channel")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('AVG(total_revenue) as avg_order_value')
            ->selectRaw('SUM(total_revenue) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN payment_status = "Credit" THEN 1 ELSE 0 END) as credit_orders')
            ->selectRaw('SUM(CASE WHEN payment_status = "Pending" THEN 1 ELSE 0 END) as pending_orders')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('sales_channel', 'like', "%{$search}%");
            })
            ->groupBy('sales_channel')
            ->orderBy($sort, $direction)
            ->get()
            ->map(function ($row) {
                $creditRate = $row->orders_count > 0
                    ? round(((float) $row->credit_orders / (float) $row->orders_count) * 100, 2)
                    : 0.0;

                $couponSuggestion = $creditRate >= 35
                    ? 'Low-risk upfront payment coupon'
                    : 'Revenue acceleration coupon';

                $target = (float) $row->avg_order_value >= 50000
                    ? 'High-ticket cart upsell'
                    : 'Basket-size growth';

                $row->credit_rate = $creditRate;
                $row->coupon_suggestion = $couponSuggestion;
                $row->target = $target;

                return $row;
            })
            ->when($focus === 'credit_heavy', fn ($collection) => $collection->filter(fn ($row) => (float) $row->credit_rate >= 35)->values())
            ->when($focus === 'high_value', fn ($collection) => $collection->filter(fn ($row) => (float) $row->avg_order_value >= 50000)->values());

        $kpis = Sale::query()
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_revenue) as gross_revenue')
            ->selectRaw('AVG(total_revenue) as avg_order_value')
            ->selectRaw('SUM(CASE WHEN payment_status = "Credit" THEN 1 ELSE 0 END) as credit_orders')
            ->first();

        return view('admin.ecommerce.modules.discounts', [
            'segments' => $segments,
            'kpis' => $kpis,
            'search' => $search,
            'focus' => $focus,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
