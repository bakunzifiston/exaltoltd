<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $payment = (string) $request->query('payment', 'all');
        $delivery = (string) $request->query('delivery', 'all');
        $channel = (string) $request->query('channel', 'online');
        $sort = (string) $request->query('sort', 'id');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'sale_date', 'sales_id', 'customer_name', 'items_count', 'order_total', 'payment_status', 'delivery_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $itemTotals = SaleItem::query()
            ->select('sale_id', DB::raw('COUNT(*) as items_count'), DB::raw('SUM(line_total) as order_total'))
            ->groupBy('sale_id');

        $orders = Sale::query()
            ->leftJoinSub($itemTotals, 'item_totals', fn ($join) => $join->on('sales.id', '=', 'item_totals.sale_id'))
            ->select('sales.*')
            ->selectRaw('COALESCE(item_totals.items_count, 0) as items_count')
            ->selectRaw('COALESCE(item_totals.order_total, sales.total_revenue) as order_total')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales.sales_id', 'like', "%{$search}%")
                        ->orWhere('sales.invoice_number', 'like', "%{$search}%")
                        ->orWhere('sales.customer_name', 'like', "%{$search}%")
                        ->orWhere('sales.customer_Phone', 'like', "%{$search}%")
                        ->orWhere('sales.barcode', 'like', "%{$search}%")
                        ->orWhere('sales.sales_channel', 'like', "%{$search}%");
                });
            })
            ->when($payment !== 'all', fn (Builder $query) => $query->where('sales.payment_status', $payment))
            ->when($delivery !== 'all', fn (Builder $query) => $query->where('sales.delivery_status', $delivery))
            ->when($channel === 'online', fn (Builder $query) => $query->where('sales.sales_channel', 'Online Store'))
            ->when($channel === 'other', fn (Builder $query) => $query->where('sales.sales_channel', '!=', 'Online Store'))
            ->orderBy(match ($sort) {
                'id' => 'sales.id',
                'sale_date' => 'sales.sale_date',
                'sales_id' => 'sales.sales_id',
                'customer_name' => 'sales.customer_name',
                'payment_status' => 'sales.payment_status',
                'delivery_status' => 'sales.delivery_status',
                'items_count' => 'items_count',
                'order_total' => 'order_total',
                default => 'sales.id',
            }, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $pendingOnlineOrders = Sale::query()
            ->where('sales_channel', 'Online Store')
            ->where('payment_status', 'Pending')
            ->count();

        return view('admin.ecommerce.modules.orders', [
            'orders' => $orders,
            'search' => $search,
            'payment' => $payment,
            'delivery' => $delivery,
            'channel' => $channel,
            'pendingOnlineOrders' => $pendingOnlineOrders,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
