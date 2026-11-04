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

class CartManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'customer_name', 'items_count', 'cart_total', 'payment_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $itemTotals = SaleItem::query()
            ->select('sale_id', DB::raw('COUNT(*) as items_count'), DB::raw('SUM(line_total) as cart_total'))
            ->groupBy('sale_id');

        $carts = Sale::query()
            ->leftJoinSub($itemTotals, 'item_totals', fn ($join) => $join->on('sales.id', '=', 'item_totals.sale_id'))
            ->select('sales.*')
            ->selectRaw('COALESCE(item_totals.items_count, 0) as items_count')
            ->selectRaw('COALESCE(item_totals.cart_total, sales.total_revenue) as cart_total')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales.customer_name', 'like', "%{$search}%")
                        ->orWhere('sales.customer_Phone', 'like', "%{$search}%")
                        ->orWhere('sales.sales_id', 'like', "%{$search}%")
                        ->orWhere('sales.invoice_number', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->whereIn('sales.payment_status', ['Pending', 'Credit']))
            ->when($status === 'checked_out', fn (Builder $query) => $query->where('sales.payment_status', 'Paid'))
            ->when($status === 'returned', fn (Builder $query) => $query->where('sales.delivery_status', 'Returned'))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.ecommerce.modules.carts', [
            'carts' => $carts,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
