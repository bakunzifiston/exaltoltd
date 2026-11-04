<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'last_sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['customer_name', 'orders_count', 'total_revenue', 'last_sale_date'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'last_sale_date';
        }

        $customers = Sale::query()
            ->selectRaw('customer_name')
            ->selectRaw("COALESCE(NULLIF(customer_Phone, ''), '-') as customer_phone")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_revenue) as total_revenue')
            ->selectRaw('SUM(CASE WHEN payment_status = "Paid" THEN total_revenue ELSE 0 END) as paid_revenue')
            ->selectRaw('MAX(sale_date) as last_sale_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_Phone', 'like', "%{$search}%");
                });
            })
            ->groupBy('customer_name', 'customer_Phone')
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $names = $customers->getCollection()->pluck('customer_name')->filter()->values();
        $linkedUsers = User::query()
            ->whereIn('name', $names)
            ->pluck('id', 'name');

        $customers->getCollection()->transform(function ($row) use ($linkedUsers) {
            $row->has_user_account = $linkedUsers->has($row->customer_name);
            $row->user_id = $linkedUsers->get($row->customer_name);

            return $row;
        });

        return view('admin.ecommerce.modules.customers', [
            'customers' => $customers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
