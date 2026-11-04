<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class OrderStatusTrackingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $stage = (string) $request->query('stage', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'sales_id', 'customer_name', 'payment_status', 'delivery_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $orders = Sale::query()
            ->select([
                'id',
                'sales_id',
                'customer_name',
                'customer_Phone',
                'sale_date',
                'payment_status',
                'delivery_status',
                'invoice_number',
                'total_revenue',
                'created_at',
                'updated_at',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('sales_id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_Phone', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            })
            ->when($stage === 'processing', fn (Builder $query) => $query->where('delivery_status', 'Pending'))
            ->when($stage === 'completed', fn (Builder $query) => $query->where('delivery_status', 'Delivered')->where('payment_status', 'Paid'))
            ->when($stage === 'at_risk', fn (Builder $query) => $query->where(function (Builder $inner): void {
                $inner->where('delivery_status', 'Returned')
                    ->orWhereIn('payment_status', ['Pending', 'Credit']);
            }))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $orders->getCollection()->transform(function (Sale $sale) {
            $timeline = $this->buildTimeline($sale);
            $sale->setAttribute('status_stage', $timeline['stage']);
            $sale->setAttribute('status_note', $timeline['note']);
            $sale->setAttribute('aging_days', $timeline['aging_days']);

            return $sale;
        });

        return view('admin.ecommerce.modules.statuses', [
            'orders' => $orders,
            'search' => $search,
            'stage' => $stage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * @return array{stage: string, note: string, aging_days: int}
     */
    private function buildTimeline(Sale $sale): array
    {
        $saleDate = $sale->sale_date ? Carbon::parse($sale->sale_date) : $sale->created_at;
        $agingDays = max((int) $saleDate->diffInDays(now()), 0);

        if ($sale->delivery_status === 'Delivered' && $sale->payment_status === 'Paid') {
            return [
                'stage' => 'Completed',
                'note' => 'Order delivered and payment settled.',
                'aging_days' => $agingDays,
            ];
        }

        if ($sale->delivery_status === 'Returned') {
            return [
                'stage' => 'Exception',
                'note' => 'Order returned; follow return/refund workflow.',
                'aging_days' => $agingDays,
            ];
        }

        if (in_array($sale->payment_status, ['Pending', 'Credit'], true)) {
            return [
                'stage' => 'Payment Follow-up',
                'note' => 'Payment still open; monitor settlement before closure.',
                'aging_days' => $agingDays,
            ];
        }

        return [
            'stage' => 'Processing',
            'note' => 'Order is in fulfillment or awaiting final delivery confirmation.',
            'aging_days' => $agingDays,
        ];
    }
}
