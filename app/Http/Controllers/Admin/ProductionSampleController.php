<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesDocuments;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\TransactionDocument;
use App\Services\ProductionSampleService;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Free samples taken from production. Staff request them; managers approve within the
 * limit in General Settings and the owner above it. Approval takes the units off the batch.
 */
class ProductionSampleController extends Controller
{
    use ManagesDocuments;

    public function __construct(private readonly ProductionSampleService $samples) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(ProductionSample::STATUSES))],
            'product_id' => ['nullable', 'integer'],
            'recipient_type' => ['nullable', Rule::in(array_keys(ProductionSample::RECIPIENT_TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $samples = $this->visible($request)
            ->with(['product:id,type', 'production:id,batch_id', 'creator:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', "%$s%")
                ->orWhere('recipient_name', 'like', "%$s%")->orWhere('purpose', 'like', "%$s%")
                ->orWhereHas('production', fn ($q) => $q->where('batch_id', 'like', "%$s%"))))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['recipient_type'] ?? null, fn ($q, $v) => $q->where('recipient_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('sample_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('sample_date', '<=', $v.' 23:59:59'))
            ->latest('sample_date')->latest('id')
            ->paginate(TablePageSize::resolve($request, 20))->withQueryString();

        return view('admin.production-samples.index', [
            'samples' => $samples,
            'filters' => $filters,
            'products' => Product::orderBy('type')->get(['id', 'type']),
            'awaiting' => $request->user()->can('approve-samples') ? ProductionSample::where('status', ProductionSample::PENDING)->count() : 0,
        ]);
    }

    public function create(): View
    {
        return view('admin.production-samples.create', $this->formData() + ['sample' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $approveNow = $request->input('action') === 'approve' && $this->samples->canApprove($request->user(), (float) $data['quantity']);

        $sample = $this->samples->create($this->attributes($data), $request->user(), $approveNow);
        $this->storeDocuments($request, $sample);

        return redirect()->route('admin.production-samples.show', $sample)
            ->with('status', $sample->status === ProductionSample::APPROVED ? 'Sample recorded and approved; the units have left stock.' : 'Sample request recorded and waiting for approval.');
    }

    public function show(Request $request, ProductionSample $productionSample): View
    {
        $this->authorizeView($request, $productionSample);

        return view('admin.production-samples.show', [
            'sample' => $productionSample->load(['product', 'production', 'customer', 'creator', 'approver', 'documents.uploader', 'events.user']),
            'canApprove' => $this->samples->canApprove($request->user(), $productionSample),
            'limit' => $this->samples->approvalLimit(),
        ]);
    }

    public function edit(Request $request, ProductionSample $productionSample): View
    {
        $this->authorizeEdit($request, $productionSample);

        return view('admin.production-samples.edit', $this->formData() + ['sample' => $productionSample]);
    }

    public function update(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        $this->authorizeEdit($request, $productionSample);
        $data = $this->validated($request);
        $batch = Production::findOrFail($data['production_id']);
        if ($batch->product_id !== (int) $data['product_id']) {
            throw ValidationException::withMessages(['production_id' => 'That batch is not of the selected product.']);
        }

        $productionSample->update($this->attributes($data));
        $this->storeDocuments($request, $productionSample);

        return redirect()->route('admin.production-samples.show', $productionSample)->with('status', 'Sample request updated.');
    }

    public function approve(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        abort_unless($request->user()->can('approve-samples'), 403);
        $data = $request->validate(['decision_notes' => ['nullable', 'string', 'max:2000']]);
        $this->samples->approve($productionSample, $request->user(), $data['decision_notes'] ?? null);

        return back()->with('status', 'Sample approved; the units have left stock.');
    }

    public function reject(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        abort_unless($request->user()->can('approve-samples'), 403);
        $data = $request->validate(['decision_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->samples->reject($productionSample, $request->user(), $data['decision_notes']);

        return back()->with('status', 'Sample request rejected.');
    }

    public function cancel(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        $this->authorizeView($request, $productionSample);
        // Withdrawing a request is the requester's call; reversing an approved sample is a manager's.
        $allowed = $productionSample->status === ProductionSample::PENDING
            ? $productionSample->created_by === $request->user()->id || $request->user()->can('approve-samples')
            : $request->user()->can('approve-samples');
        abort_unless($allowed, 403);
        $data = $request->validate(['decision_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->samples->cancel($productionSample, $request->user(), $data['decision_notes']);

        return back()->with('status', 'Sample cancelled.');
    }

    /**
     * Removes a sample request that has no stock effect. An approved sample has taken units off its
     * batch, so it must be reversed first ("Reverse and restock", which puts them back and records
     * the movement); only then can it be deleted. Its attached documents go with it.
     */
    public function destroy(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        abort_unless($request->user()->can('delete-records'), 403);

        if ($productionSample->status === ProductionSample::APPROVED) {
            return back()->withErrors([
                'sample' => $productionSample->reference.' is approved, so its units have left stock. Open it and use "Reverse and restock" first, then delete it.',
            ]);
        }

        $reference = $productionSample->reference;
        DB::transaction(function () use ($productionSample): void {
            $productionSample->documents()->get()->each->delete();
            $productionSample->delete();
        });

        return redirect()->route('admin.production-samples.index')->with('status', 'Sample '.$reference.' deleted.');
    }

    public function uploadDocuments(Request $request, ProductionSample $productionSample): RedirectResponse
    {
        $this->authorizeView($request, $productionSample);
        $this->storeDocuments($request, $productionSample, required: true);

        return back()->with('status', 'Document attached.');
    }

    public function downloadDocument(Request $request, ProductionSample $productionSample, TransactionDocument $document): StreamedResponse
    {
        $this->authorizeView($request, $productionSample);

        return $this->download($productionSample, $document);
    }

    public function destroyDocument(Request $request, ProductionSample $productionSample, TransactionDocument $document): RedirectResponse
    {
        $this->authorizeEdit($request, $productionSample);
        $this->removeDocument($productionSample, $document);

        return back()->with('status', 'Document removed.');
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'production_id' => ['required', 'integer', 'exists:productions,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'sample_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'recipient_type' => ['required', Rule::in(array_keys(ProductionSample::RECIPIENT_TYPES))],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'recipient_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'requested_by_name' => ['nullable', 'string', 'max:255'],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], ['recipient_name.required_without' => 'Choose a customer or type who received the samples.']);
    }

    private function attributes(array $data): array
    {
        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        return [
            'product_id' => $data['product_id'],
            'production_id' => $data['production_id'],
            'quantity' => $data['quantity'],
            'sample_date' => $data['sample_date'],
            'recipient_type' => $data['recipient_type'],
            'customer_id' => $customer?->id,
            'recipient_name' => ($data['recipient_name'] ?? null) ?: $customer?->name,
            'purpose' => $data['purpose'],
            'requested_by_name' => $data['requested_by_name'] ?? null,
            'received_by_name' => $data['received_by_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function formData(): array
    {
        return [
            'products' => Product::orderBy('type')->get(['id', 'type']),
            // Only stock that may go to a customer: released, in date, with units left.
            'batches' => Production::query()->sellable()->where('quantity_remaining', '>', 0)->fefo()
                ->get(['id', 'product_id', 'batch_id', 'quantity_remaining', 'best_before_date'])
                ->map(fn (Production $b) => ['id' => $b->id, 'product_id' => $b->product_id, 'label' => $b->batch_id.' · '.rtrim(rtrim(number_format((float) $b->quantity_remaining, 2), '0'), '.').' left'.($b->best_before_date ? ' · BB '.$b->best_before_date->format('d M Y') : '')]),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'limit' => $this->samples->approvalLimit(),
        ];
    }

    /** Staff see the samples they requested; approvers see all. */
    private function visible(Request $request)
    {
        return ProductionSample::query()->unless($request->user()->can('approve-samples'),
            fn ($q) => $q->where('created_by', $request->user()->id));
    }

    private function authorizeView(Request $request, ProductionSample $sample): void
    {
        abort_unless($this->visible($request)->whereKey($sample->id)->exists(), 403);
    }

    private function authorizeEdit(Request $request, ProductionSample $sample): void
    {
        $this->authorizeView($request, $sample);
        abort_unless($sample->status === ProductionSample::PENDING, 403, 'Only a request still waiting for approval can be changed.');
    }
}
