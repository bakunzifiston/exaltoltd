<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\TablePageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $event = (string) $request->query('event', 'all');
        $sort = (string) $request->query('sort', 'sale_date');
        $direction = (string) $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['sale_date', 'customer_name', 'payment_status', 'delivery_status'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'sale_date';
        }

        $notifications = Sale::query()
            ->select([
                'id',
                'sales_id',
                'customer_name',
                'customer_Phone',
                'payment_status',
                'delivery_status',
                'sale_date',
                'invoice_number',
                'total_revenue',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_Phone', 'like', "%{$search}%")
                        ->orWhere('sales_id', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%");
                });
            })
            ->when($event === 'delivery_pending', fn (Builder $query) => $query->where('delivery_status', 'Pending'))
            ->when($event === 'delivered', fn (Builder $query) => $query->where('delivery_status', 'Delivered'))
            ->when($event === 'payment_pending', fn (Builder $query) => $query->whereIn('payment_status', ['Pending', 'Credit']))
            ->when($event === 'payment_received', fn (Builder $query) => $query->where('payment_status', 'Paid'))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $notifications->getCollection()->transform(function (Sale $sale) {
            $eventType = 'Order update';
            $eventMessage = 'Send general order status notification.';

            if ($sale->delivery_status === 'Pending') {
                $eventType = 'Delivery pending';
                $eventMessage = 'Remind customer that the order is being prepared for delivery.';
            } elseif ($sale->delivery_status === 'Delivered') {
                $eventType = 'Delivered confirmation';
                $eventMessage = 'Send delivery confirmation and request post-delivery feedback.';
            }

            if (in_array($sale->payment_status, ['Pending', 'Credit'], true)) {
                $eventType = 'Payment follow-up';
                $eventMessage = 'Notify customer about outstanding payment or credit settlement.';
            } elseif ($sale->payment_status === 'Paid') {
                $eventType = 'Payment received';
                $eventMessage = 'Send payment receipt confirmation with invoice reference.';
            }

            $sale->setAttribute('event_type', $eventType);
            $sale->setAttribute('event_message', $eventMessage);

            return $sale;
        });

        return view('admin.ecommerce.modules.notifications', [
            'notifications' => $notifications,
            'search' => $search,
            'event' => $event,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
}
