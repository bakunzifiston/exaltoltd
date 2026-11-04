<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesDocuments;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockReturn;
use App\Models\TransactionDocument;
use App\Services\RevenueRecognitionService;
use App\Services\StockReturnService;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock returns against delivered orders.
 *
 * Sales staff record returns on their own orders; inspection and approval, which put stock
 * back and credit the customer, are for managers and the owner.
 */
class StockReturnController extends Controller
{
    use ManagesDocuments;

    public function __construct(private readonly StockReturnService $returns) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(StockReturn::STATUSES))],
            'customer_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'reason' => ['nullable', Rule::in(array_keys(StockReturn::REASONS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $returns = $this->visible($request)
            ->with(['sale:id,sales_id,invoice_number', 'creator:id,name'])
            ->withSum('items as quantity_returned', 'quantity_returned')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', "%$s%")
                ->orWhere('customer_name', 'like', "%$s%")->orWhere('delivery_note', 'like', "%$s%")
                ->orWhereHas('sale', fn ($q) => $q->where('sales_id', 'like', "%$s%")->orWhere('invoice_number', 'like', "%$s%"))))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($filters['reason'] ?? null, fn ($q, $v) => $q->where('reason', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($q) => $q->where('product_id', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('return_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('return_date', '<=', $v.' 23:59:59'))
            ->latest('return_date')->latest('id')
            ->paginate(TablePageSize::resolve($request, 20))->withQueryString();

        return view('admin.stock-returns.index', [
            'returns' => $returns,
            'filters' => $filters,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('type')->get(['id', 'type']),
            'awaiting' => $request->user()->can('approve-returns') ? StockReturn::where('status', StockReturn::PENDING_INSPECTION)->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer_id') ?: null;
        $sale = $request->integer('sale_id') ? $this->visibleSales($request)->find($request->integer('sale_id')) : null;

        return view('admin.stock-returns.create', $this->formData($request, $customerId ?? $sale?->customer_id, $sale) + ['return' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        abort_unless($this->visibleSales($request)->whereKey($data['sale_id'])->exists(), 403);

        $return = $this->returns->save($data, $request->user(), submit: $request->input('action') === 'submit');
        $this->storeDocuments($request, $return);

        return redirect()->route('admin.stock-returns.show', $return)
            ->with('status', $return->status === StockReturn::DRAFT ? 'Return saved as a draft.' : 'Return submitted for inspection.');
    }

    public function show(Request $request, StockReturn $stockReturn): View
    {
        $this->authorizeView($request, $stockReturn);
        $stockReturn->load(['items.product', 'items.production', 'items.saleItem', 'sale.payments', 'customer', 'creator', 'decider', 'documents.uploader', 'events.user']);

        return view('admin.stock-returns.show', [
            'return' => $stockReturn,
            'sale' => $stockReturn->sale,
            'otherReturns' => $stockReturn->sale->stockReturns()->whereKeyNot($stockReturn->id)->latest('id')->get(),
        ]);
    }

    public function edit(Request $request, StockReturn $stockReturn): View
    {
        $this->authorizeEdit($request, $stockReturn);

        return view('admin.stock-returns.edit', $this->formData($request, $stockReturn->customer_id, $stockReturn->sale) + ['return' => $stockReturn->load('items')]);
    }

    public function update(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $this->authorizeEdit($request, $stockReturn);
        $data = $this->validated($request);
        $data['sale_id'] = $stockReturn->sale_id;

        $this->returns->save($data, $request->user(), $stockReturn, submit: $request->input('action') === 'submit');
        $this->storeDocuments($request, $stockReturn);

        return redirect()->route('admin.stock-returns.show', $stockReturn)->with('status', 'Return updated.');
    }

    public function submit(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $this->authorizeEdit($request, $stockReturn);
        $this->returns->submit($stockReturn, $request->user());

        return back()->with('status', 'Return submitted for inspection.');
    }

    public function approve(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        abort_unless($request->user()->can('approve-returns'), 403);
        $data = $request->validate([
            'inspection' => ['required', 'array'],
            'inspection.*.quantity_restocked' => ['nullable', 'numeric', 'min:0'],
            'inspection.*.quantity_damaged' => ['nullable', 'numeric', 'min:0'],
            'inspection.*.quantity_expired' => ['nullable', 'numeric', 'min:0'],
            'inspection.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'inspection.*.condition_notes' => ['nullable', 'string', 'max:255'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->returns->approve($stockReturn, $data['inspection'], $request->user(), $data['inspection_notes'] ?? null);

        return back()->with('status', 'Return approved: sellable units are back in stock and the order has been credited.');
    }

    public function reject(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        abort_unless($request->user()->can('approve-returns'), 403);
        $data = $request->validate(['inspection_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->returns->reject($stockReturn, $request->user(), $data['inspection_notes']);

        return back()->with('status', 'Return rejected. Nothing was added to stock and the order was not credited.');
    }

    public function cancel(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $this->authorizeView($request, $stockReturn);
        abort_unless($stockReturn->created_by === $request->user()->id || $request->user()->can('approve-returns'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->returns->cancel($stockReturn, $request->user(), $data['reason']);

        return back()->with('status', 'Return cancelled.');
    }

    public function uploadDocuments(Request $request, StockReturn $stockReturn): RedirectResponse
    {
        $this->authorizeView($request, $stockReturn);
        $this->storeDocuments($request, $stockReturn, required: true);

        return back()->with('status', 'Document attached.');
    }

    public function downloadDocument(Request $request, StockReturn $stockReturn, TransactionDocument $document): StreamedResponse
    {
        $this->authorizeView($request, $stockReturn);

        return $this->download($stockReturn, $document);
    }

    public function destroyDocument(Request $request, StockReturn $stockReturn, TransactionDocument $document): RedirectResponse
    {
        $this->authorizeEdit($request, $stockReturn);
        $this->removeDocument($stockReturn, $document);

        return back()->with('status', 'Document removed.');
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', Rule::in(array_keys(StockReturn::REASONS))],
            'reason_other' => ['nullable', 'required_if:reason,other', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.sale_item_id' => ['required', 'integer'],
            'lines.*.quantity_sold_reported' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_returned' => ['nullable', 'numeric', 'min:0'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], ['reason_other.required_if' => 'Describe the reason for the return.']);
    }

    private function formData(Request $request, ?int $customerId, ?Sale $sale): array
    {
        $sales = $customerId
            ? $this->visibleSales($request)->where('customer_id', $customerId)
                ->where('delivery_status', RevenueRecognitionService::DELIVERED)
                ->latest('sale_date')->get(['id', 'sales_id', 'invoice_number', 'sale_date', 'total_revenue'])
            : collect();

        return [
            'customers' => Customer::query()->whereHas('sales', fn ($q) => $q->where('delivery_status', RevenueRecognitionService::DELIVERED))
                ->orderBy('name')->get(['id', 'name']),
            'customerId' => $customerId,
            'sales' => $sales,
            'sale' => $sale,
            'lines' => $sale ? $this->returns->returnableLines($sale, $request->route('stockReturn')) : collect(),
        ];
    }

    /** Sales staff see returns on their own orders only. */
    private function visible(Request $request)
    {
        return StockReturn::query()->unless($request->user()->can('manage-all-sales'),
            fn ($q) => $q->whereHas('sale', fn ($q) => $q->where('created_by', $request->user()->id)));
    }

    private function visibleSales(Request $request)
    {
        return Sale::query()->unless($request->user()->can('manage-all-sales'), fn ($q) => $q->where('created_by', $request->user()->id));
    }

    private function authorizeView(Request $request, StockReturn $return): void
    {
        abort_unless($this->visible($request)->whereKey($return->id)->exists(), 403);
    }

    private function authorizeEdit(Request $request, StockReturn $return): void
    {
        $this->authorizeView($request, $return);
        abort_unless($return->isDraft(), 403, 'Only a draft return can be changed.');
        abort_unless($return->created_by === $request->user()->id || $request->user()->can('manage-all-sales'), 403);
    }
}
