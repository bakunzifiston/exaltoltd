<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VariantManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'variant_label');
        $direction = (string) $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['variant_label', 'products_count', 'stock_on_hand', 'sold_units'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'variant_label';
        }

        $inventorySub = InventoryRecord::query()
            ->select('product_id', DB::raw('SUM(quantity_in - quantity_out) as stock_on_hand'))
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        $salesSub = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold_units'))
            ->groupBy('product_id');

        $products = Product::query()
            ->leftJoinSub($inventorySub, 'inventory_totals', fn ($join) => $join->on('products.id', '=', 'inventory_totals.product_id'))
            ->leftJoinSub($salesSub, 'sales_totals', fn ($join) => $join->on('products.id', '=', 'sales_totals.product_id'))
            ->select(
                'products.id',
                'products.type',
                'products.name',
                'products.description',
                'products.barcode'
            )
            ->selectRaw('COALESCE(inventory_totals.stock_on_hand, 0) as stock_on_hand')
            ->selectRaw('COALESCE(sales_totals.sold_units, 0) as sold_units')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('products.type', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('products.barcode', 'like', "%{$search}%")
                        ->orWhere('products.description', 'like', "%{$search}%");
                });
            })
            ->get();

        $variantRows = $products
            ->groupBy(function (Product $product): string {
                $source = $product->barcode ?: trim($product->name.' '.$product->type.' '.$product->description);
                $normalized = Str::of($source)
                    ->upper()
                    ->replaceMatches('/[^A-Z0-9]+/', ' ')
                    ->trim()
                    ->toString();

                return $normalized !== '' ? $normalized : 'UNCLASSIFIED';
            })
            ->map(function ($group, string $key): array {
                $first = $group->first();

                return [
                    'variant_key' => $key,
                    'variant_label' => $first->barcode ?: Str::title(Str::lower($key)),
                    'type_preview' => $first->type,
                    'products_count' => $group->count(),
                    'stock_on_hand' => (float) $group->sum(fn ($product) => (float) $product->stock_on_hand),
                    'sold_units' => (float) $group->sum(fn ($product) => (float) $product->sold_units),
                ];
            })
            ->values();

        $variantRows = $direction === 'desc'
            ? $variantRows->sortByDesc($sort, SORT_NATURAL | SORT_FLAG_CASE)->values()
            : $variantRows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $currentPage = max((int) $request->query('page', 1), 1);
        $perPage = 15;
        $offset = ($currentPage - 1) * $perPage;
        $items = $variantRows->slice($offset, $perPage)->values();

        $variants = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $variantRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.ecommerce.modules.variants', [
            'variants' => $variants,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
