<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\SaleItem;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'category');
        $direction = (string) $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['category', 'products_count', 'stock_on_hand', 'sold_units'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'category';
        }

        $inventorySub = InventoryRecord::query()
            ->select('product_id', DB::raw('SUM(quantity_in - quantity_out) as stock_on_hand'))
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        $salesSub = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold_units'))
            ->groupBy('product_id');

        $categories = Product::query()
            ->leftJoinSub($inventorySub, 'inventory_totals', fn ($join) => $join->on('products.id', '=', 'inventory_totals.product_id'))
            ->leftJoinSub($salesSub, 'sales_totals', fn ($join) => $join->on('products.id', '=', 'sales_totals.product_id'))
            ->selectRaw("COALESCE(NULLIF(products.type, ''), 'Uncategorized') as category")
            ->selectRaw('COUNT(products.id) as products_count')
            ->selectRaw('COALESCE(SUM(inventory_totals.stock_on_hand), 0) as stock_on_hand')
            ->selectRaw('COALESCE(SUM(sales_totals.sold_units), 0) as sold_units')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('products.type', 'like', "%{$search}%");
            })
            ->groupBy('category')
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.ecommerce.modules.categories', [
            'categories' => $categories,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
