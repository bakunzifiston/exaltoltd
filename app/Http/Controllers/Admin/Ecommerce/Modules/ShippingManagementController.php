<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $scope = (string) $request->query('scope', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'sales_id', 'customer_name', 'sales_channel', 'delivery_status', 'total_revenue'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $shipments = Sale::query()
            ->select([
                'id',
                'sales_id',
                'customer_name',
                'customer_Phone',
                'sale_date',
                'sales_channel',
                'delivery_status',
                'payment_status',
                'invoice_number',
                'total_revenue',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales_id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_Phone', 'like', "%{$search}%")
                        ->orWhere('sales_channel', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            })
            ->when($scope === 'pending_dispatch', fn (Builder $query) => $query->where('delivery_status', 'Pending'))
            ->when($scope === 'delivered', fn (Builder $query) => $query->where('delivery_status', 'Delivered'))
            ->when($scope === 'returned', fn (Builder $query) => $query->where('delivery_status', 'Returned'))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $shipments->getCollection()->transform(function (Sale $sale) {
            $dispatchPriority = 'Normal';
            $workflowStep = 'Monitor delivery lifecycle';

            if ($sale->delivery_status === 'Pending' && $sale->payment_status === 'Paid') {
                $dispatchPriority = 'High';
                $workflowStep = 'Dispatch immediately (payment cleared)';
            } elseif ($sale->delivery_status === 'Pending' && in_array($sale->payment_status, ['Pending', 'Credit'], true)) {
                $dispatchPriority = 'Review';
                $workflowStep = 'Confirm payment terms before dispatch';
            } elseif ($sale->delivery_status === 'Delivered') {
                $dispatchPriority = 'Closed';
                $workflowStep = 'Track proof-of-delivery and feedback';
            } elseif ($sale->delivery_status === 'Returned') {
                $dispatchPriority = 'Exception';
                $workflowStep = 'Escalate return logistics and restocking';
            }

            $sale->setAttribute('dispatch_priority', $dispatchPriority);
            $sale->setAttribute('workflow_step', $workflowStep);

            return $sale;
        });

        $channelStats = Sale::query()
            ->selectRaw("COALESCE(NULLIF(sales_channel, ''), 'Unassigned') as sales_channel")
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(CASE WHEN delivery_status = "Pending" THEN 1 ELSE 0 END) as pending_orders')
            ->selectRaw('SUM(CASE WHEN delivery_status = "Delivered" THEN 1 ELSE 0 END) as delivered_orders')
            ->selectRaw('SUM(CASE WHEN delivery_status = "Returned" THEN 1 ELSE 0 END) as returned_orders')
            ->groupBy('sales_channel')
            ->orderByDesc('total_orders')
            ->limit(6)
            ->get();

        return view('admin.ecommerce.modules.shipping', [
            'shipments' => $shipments,
            'channelStats' => $channelStats,
            'search' => $search,
            'scope' => $scope,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
