<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Production;
use App\Models\SaleItem;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $stockFilter = (string) $request->query('stock', 'all');
        $category = trim((string) $request->query('category', ''));
        $sort = (string) $request->query('sort', 'type');
        $direction = (string) $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['type', 'name', 'stock_on_hand', 'available_finished_goods', 'sold_units'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'type';
        }

        $productionSub = Production::query()
            ->select('product_id', DB::raw('SUM(quantity_remaining) as available_finished_goods'))
            ->groupBy('product_id');

        $salesSub = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold_units'))
            ->groupBy('product_id');

        $products = Product::query()
            ->leftJoinSub($productionSub, 'production_totals', fn ($join) => $join->on('products.id', '=', 'production_totals.product_id'))
            ->leftJoinSub($salesSub, 'sales_totals', fn ($join) => $join->on('products.id', '=', 'sales_totals.product_id'))
            ->select('products.*')
            ->selectRaw('COALESCE(production_totals.available_finished_goods, 0) as stock_on_hand')
            ->selectRaw('COALESCE(production_totals.available_finished_goods, 0) as available_finished_goods')
            ->selectRaw('COALESCE(sales_totals.sold_units, 0) as sold_units')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('products.type', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('products.barcode', 'like', "%{$search}%")
                        ->orWhere('products.description', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn (Builder $query) => $query->where('products.type', $category))
            ->when($stockFilter === 'in_stock', fn (Builder $query) => $query->whereRaw('COALESCE(production_totals.available_finished_goods, 0) > 0'))
            ->when($stockFilter === 'out_of_stock', fn (Builder $query) => $query->whereRaw('COALESCE(production_totals.available_finished_goods, 0) <= 0'))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.ecommerce.modules.products', [
            'products' => $products,
            'search' => $search,
            'stockFilter' => $stockFilter,
            'category' => $category,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
