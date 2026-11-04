<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\InventoryRecord;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cash paid to suppliers — the outflow side of the cash flow statement.
 *
 * Recording a payment here also settles the payable on the originating purchase, so the
 * balance sheet and the cash flow statement stay consistent from a single entry.
 */
class SupplierPaymentController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance.supplier-payments', [
            'payments' => SupplierPayment::query()->with(['supplier', 'inventoryRecord', 'cashAccount'])
                ->orderByDesc('paid_at')->orderByDesc('id')->paginate(TablePageSize::resolve($request, 25))->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => SupplierPayment::METHODS,
            'unpaid' => InventoryRecord::query()
                ->whereNotNull('total_amount')
                ->whereColumn('amount_paid', '<', 'total_amount')
                ->orderByDesc('record_date')->limit(200)
                ->get(['id', 'item_name', 'supplier_name', 'record_date', 'total_amount', 'amount_paid']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_record_id' => ['nullable', 'integer', 'exists:inventory_records,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999', 'decimal:0,2'],
            'method' => ['required', 'in:'.implode(',', SupplierPayment::METHODS)],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['recorded_by'] = $request->user()->id;

        $payment = SupplierPayment::create($data);
        if ($payment->inventoryRecord) {
            $this->recalculatePayable($payment->inventoryRecord);
        }

        return back()->with('status', 'Supplier payment recorded.');
    }

    public function destroy(SupplierPayment $supplierPayment): RedirectResponse
    {
        $record = $supplierPayment->inventoryRecord;
        $supplierPayment->delete();
        if ($record) {
            $this->recalculatePayable($record);
        }

        return back()->with('status', 'Supplier payment removed.');
    }

    /** Keep the running settled total on a purchase in step with the payments recorded. */
    private function recalculatePayable(InventoryRecord $record): void
    {
        $paid = (float) SupplierPayment::query()->where('inventory_record_id', $record->id)->sum('amount');
        $total = (float) $record->total_amount;

        $record->forceFill([
            'amount_paid' => round($paid, 2),
            'payment_status' => $paid <= 0 ? 'Unpaid' : ($paid + 0.01 >= $total ? 'Paid' : 'Partial'),
        ])->saveQuietly();
    }
}
