<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\SaleItem;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'customer_name', 'product_name', 'line_total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $reviews = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('sale_items.id as sale_item_id')
            ->selectRaw('sales.id as sale_id')
            ->selectRaw('sales.customer_name')
            ->selectRaw("COALESCE(NULLIF(sales.customer_Phone, ''), '-') as customer_phone")
            ->selectRaw('sales.sale_date')
            ->selectRaw('sales.delivery_status')
            ->selectRaw('sales.payment_status')
            ->selectRaw('products.type as product_name')
            ->selectRaw('sale_items.quantity_sold')
            ->selectRaw('sale_items.line_total')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales.customer_name', 'like', "%{$search}%")
                        ->orWhere('sales.customer_Phone', 'like', "%{$search}%")
                        ->orWhere('products.type', 'like', "%{$search}%");
                });
            })
            ->when($status === 'ready', fn (Builder $query) => $query->where('sales.delivery_status', 'Delivered'))
            ->when($status === 'pending', fn (Builder $query) => $query->where('sales.delivery_status', '!=', 'Delivered'))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $reviews->getCollection()->transform(function ($row) {
            $row->review_state = $row->delivery_status === 'Delivered' ? 'Ready for review request' : 'Pending delivery';
            $row->suggested_rating = $row->payment_status === 'Paid' ? 5 : 4;

            return $row;
        });

        return view('admin.ecommerce.modules.reviews', [
            'reviews' => $reviews,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
