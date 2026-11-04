<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $scope = (string) $request->query('scope', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'sales_id', 'customer_name', 'total_revenue', 'delivery_status', 'payment_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $returns = Sale::query()
            ->select([
                'id',
                'sales_id',
                'customer_name',
                'customer_Phone',
                'invoice_number',
                'sale_date',
                'delivery_status',
                'payment_status',
                'total_revenue',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales_id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_Phone', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            })
            ->when($scope === 'returned_only', fn (Builder $query) => $query->where('delivery_status', 'Returned'))
            ->when($scope === 'refund_candidates', fn (Builder $query) => $query->where('delivery_status', 'Returned')->where('payment_status', 'Paid'))
            ->when($scope === 'credit_reversals', fn (Builder $query) => $query->where('delivery_status', 'Returned')->whereIn('payment_status', ['Pending', 'Credit']))
            ->when($scope === 'all', fn (Builder $query) => $query->where(function (Builder $inner): void {
                $inner->where('delivery_status', 'Returned')
                    ->orWhereIn('payment_status', ['Pending', 'Credit']);
            }))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $returns->getCollection()->transform(function (Sale $sale) {
            $recommendedAction = 'Monitor';
            $estimatedRefund = 0.0;

            if ($sale->delivery_status === 'Returned' && $sale->payment_status === 'Paid') {
                $recommendedAction = 'Issue refund';
                $estimatedRefund = (float) $sale->total_revenue;
            } elseif ($sale->delivery_status === 'Returned' && in_array($sale->payment_status, ['Pending', 'Credit'], true)) {
                $recommendedAction = 'Reverse receivable';
            } elseif (in_array($sale->payment_status, ['Pending', 'Credit'], true)) {
                $recommendedAction = 'Hold settlement until delivery confirmation';
            }

            $sale->setAttribute('recommended_action', $recommendedAction);
            $sale->setAttribute('estimated_refund', $estimatedRefund);

            return $sale;
        });

        return view('admin.ecommerce.modules.returns', [
            'returns' => $returns,
            'search' => $search,
            'scope' => $scope,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
