<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventorySyncController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'product_name');
        $direction = (string) $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['product_name', 'stock_on_hand', 'finished_goods', 'sold_units', 'sellable_qty', 'sync_gap'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'product_name';
        }

        $inventorySub = InventoryRecord::query()
            ->select('product_id', DB::raw('SUM(quantity_in - quantity_out) as stock_on_hand'))
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        $productionSub = Production::query()
            ->select('product_id', DB::raw('SUM(quantity_remaining) as finished_goods'))
            ->groupBy('product_id');

        $salesSub = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold_units'))
            ->groupBy('product_id');

        $rows = Product::query()
            ->leftJoinSub($inventorySub, 'inventory_totals', fn ($join) => $join->on('products.id', '=', 'inventory_totals.product_id'))
            ->leftJoinSub($productionSub, 'production_totals', fn ($join) => $join->on('products.id', '=', 'production_totals.product_id'))
            ->leftJoinSub($salesSub, 'sales_totals', fn ($join) => $join->on('products.id', '=', 'sales_totals.product_id'))
            ->select('products.id', 'products.type as product_name', 'products.name', 'products.barcode')
            ->selectRaw('COALESCE(inventory_totals.stock_on_hand, 0) as stock_on_hand')
            ->selectRaw('COALESCE(production_totals.finished_goods, 0) as finished_goods')
            ->selectRaw('COALESCE(sales_totals.sold_units, 0) as sold_units')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('products.type', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('products.barcode', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(function ($row) {
                $stockOnHand = (float) $row->stock_on_hand;
                $finishedGoods = (float) $row->finished_goods;
                $soldUnits = (float) $row->sold_units;

                $sellableQty = max(min($stockOnHand, $finishedGoods), 0.0);
                $syncGap = round($stockOnHand - $finishedGoods, 2);

                $syncStatus = 'Balanced';
                if ($sellableQty <= 0) {
                    $syncStatus = 'Out of stock';
                } elseif (abs($syncGap) > 0.01) {
                    $syncStatus = $syncGap > 0 ? 'Inventory ahead' : 'Production ahead';
                }

                $row->sellable_qty = $sellableQty;
                $row->sync_gap = $syncGap;
                $row->sync_status = $syncStatus;

                return $row;
            });

        $rows = match ($status) {
            'out_of_stock' => $rows->filter(fn ($row) => (float) $row->sellable_qty <= 0)->values(),
            'inventory_ahead' => $rows->filter(fn ($row) => (float) $row->sync_gap > 0.01)->values(),
            'production_ahead' => $rows->filter(fn ($row) => (float) $row->sync_gap < -0.01)->values(),
            'balanced' => $rows->filter(fn ($row) => abs((float) $row->sync_gap) <= 0.01 && (float) $row->sellable_qty > 0)->values(),
            default => $rows,
        };

        $rows = $direction === 'desc'
            ? $rows->sortByDesc($sort, SORT_NATURAL | SORT_FLAG_CASE)->values()
            : $rows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $currentPage = max((int) $request->query('page', 1), 1);
        $perPage = 15;
        $offset = ($currentPage - 1) * $perPage;
        $items = $rows->slice($offset, $perPage)->values();

        $syncRows = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.ecommerce.modules.inventory-sync', [
            'syncRows' => $syncRows,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
