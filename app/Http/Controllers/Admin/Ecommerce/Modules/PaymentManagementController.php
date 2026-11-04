<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'sales_id', 'customer_name', 'total_revenue', 'payment_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $payments = Sale::query()
            ->select([
                'id',
                'sales_id',
                'customer_name',
                'customer_Phone',
                'invoice_number',
                'sale_date',
                'payment_status',
                'delivery_status',
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
            ->when($status !== 'all', fn (Builder $query) => $query->where('payment_status', $status))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $payments->getCollection()->transform(function (Sale $sale) {
            $outstandingAmount = $sale->payment_status === 'Paid' ? 0.0 : (float) $sale->total_revenue;
            $settlementPriority = match ($sale->payment_status) {
                'Credit' => 'High',
                'Pending' => 'Medium',
                default => 'Settled',
            };

            $sale->setAttribute('outstanding_amount', $outstandingAmount);
            $sale->setAttribute('settlement_priority', $settlementPriority);

            return $sale;
        });

        $totals = Sale::query()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(total_revenue) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN payment_status = "Paid" THEN total_revenue ELSE 0 END) as paid_amount')
            ->selectRaw('SUM(CASE WHEN payment_status != "Paid" THEN total_revenue ELSE 0 END) as outstanding_amount')
            ->first();

        return view('admin.ecommerce.modules.payments', [
            'payments' => $payments,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $totals,
        ]);
    }
}
