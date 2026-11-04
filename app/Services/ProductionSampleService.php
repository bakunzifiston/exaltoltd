<?php

namespace App\Services;

use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Samples taken from a production batch and given away.
 *
 * A sample is requested, then approved: approval takes the units off the batch, records a
 * stock movement, and freezes the sample's cost at the batch's raw-material cost per unit.
 * No sale, invoice or revenue is ever created. Larger requests need the owner's approval.
 */
class ProductionSampleService
{
    public const DEFAULT_APPROVAL_LIMIT = 24;

    public function __construct(
        private readonly ProductionCostingService $costing,
        private readonly SiteSettingsService $settings,
    ) {}

    /** The most a manager may approve in one sample; above it only the owner can. */
    public function approvalLimit(): float
    {
        return (float) ($this->settings->get('sample_approval_limit') ?: self::DEFAULT_APPROVAL_LIMIT);
    }

    public function canApprove(User $user, ProductionSample|float $sampleOrQuantity): bool
    {
        $quantity = $sampleOrQuantity instanceof ProductionSample ? (float) $sampleOrQuantity->quantity : $sampleOrQuantity;

        return $user->can('approve-samples') && ($user->isOwner() || $quantity <= $this->approvalLimit() + 0.0001);
    }

    public function create(array $data, User $user, bool $approveNow = false): ProductionSample
    {
        return DB::transaction(function () use ($data, $user, $approveNow) {
            $batch = Production::query()->lockForUpdate()->findOrFail($data['production_id']);
            $this->checkBatch($batch, (int) $data['product_id'], (float) $data['quantity']);

            $sample = (new ProductionSample($data + ['created_by' => $user->id]))->forceFill(['status' => ProductionSample::PENDING]);
            $sample->save();

            if ($approveNow) {
                $this->approve($sample, $user);
            }

            return $sample->fresh();
        });
    }

    public function approve(ProductionSample $sample, User $user, ?string $notes = null): ProductionSample
    {
        return DB::transaction(function () use ($sample, $user, $notes) {
            $sample = ProductionSample::query()->lockForUpdate()->findOrFail($sample->id);
            $this->expect($sample, ProductionSample::PENDING);
            if (! $this->canApprove($user, $sample)) {
                throw ValidationException::withMessages(['status' => sprintf('Samples above %s units need the owner\'s approval.', rtrim(rtrim(number_format($this->approvalLimit(), 2), '0'), '.'))]);
            }

            $batch = Production::query()->lockForUpdate()->findOrFail($sample->production_id);
            $this->checkBatch($batch, (int) $sample->product_id, (float) $sample->quantity);

            $batch->quantity_remaining = round((float) $batch->quantity_remaining - (float) $sample->quantity, 2);
            $batch->save();

            StockMovement::record(
                type: 'sample',
                quantity: -1 * (float) $sample->quantity,
                productId: $sample->product_id,
                productionId: $sample->production_id,
                referenceType: 'production_sample',
                referenceId: $sample->id,
                notes: $sample->reference.': sample for '.$sample->recipient_name.' ('.$sample->purpose.')',
            );

            // Cost is frozen on approval, on the same basis as cost of goods sold.
            $unit = $this->costing->unitCost($batch);
            $sample->forceFill([
                'status' => ProductionSample::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'decision_notes' => $notes,
                'unit_cost' => $unit,
                'cost_value' => $unit === null ? null : round($unit * (float) $sample->quantity, 2),
            ])->save();

            return $sample;
        });
    }

    public function reject(ProductionSample $sample, User $user, string $notes): void
    {
        DB::transaction(function () use ($sample, $user, $notes) {
            $sample = ProductionSample::query()->lockForUpdate()->findOrFail($sample->id);
            $this->expect($sample, ProductionSample::PENDING);
            $sample->forceFill(['status' => ProductionSample::REJECTED, 'approved_by' => $user->id, 'approved_at' => now(), 'decision_notes' => $notes])->save();
        });
    }

    /**
     * Withdraw a request, or reverse an approved sample that never left the building, in
     * which case its units go back on the batch.
     */
    public function cancel(ProductionSample $sample, User $user, string $notes): void
    {
        DB::transaction(function () use ($sample, $notes) {
            $sample = ProductionSample::query()->lockForUpdate()->findOrFail($sample->id);
            $this->expect($sample, ProductionSample::PENDING, ProductionSample::APPROVED);

            if ($sample->status === ProductionSample::APPROVED) {
                $batch = Production::query()->lockForUpdate()->findOrFail($sample->production_id);
                $batch->quantity_remaining = round((float) $batch->quantity_remaining + (float) $sample->quantity, 2);
                $batch->save();
                StockMovement::record(
                    type: 'sample_reversal',
                    quantity: (float) $sample->quantity,
                    productId: $sample->product_id,
                    productionId: $sample->production_id,
                    referenceType: 'production_sample',
                    referenceId: $sample->id,
                    notes: $sample->reference.': sample cancelled, units back in stock ('.$notes.')',
                );
            }

            $sample->forceFill(['status' => ProductionSample::CANCELLED, 'decision_notes' => $notes])->save();
        });
    }

    private function checkBatch(Production $batch, int $productId, float $quantity): void
    {
        $errors = [];
        if ($batch->product_id !== $productId) {
            $errors['production_id'] = 'That batch is not of the selected product.';
        } elseif ($reason = $batch->unsellableReason()) {
            $errors['production_id'] = 'Samples can only come from a batch that may be given to customers; batch '.$batch->batch_id.' is '.$reason.'.';
        } elseif ($quantity > (float) $batch->quantity_remaining + 0.0001) {
            $errors['quantity'] = 'Batch '.$batch->batch_id.' has only '.rtrim(rtrim(number_format((float) $batch->quantity_remaining, 2), '0'), '.').' units left.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function expect(ProductionSample $sample, string ...$statuses): void
    {
        if (! in_array($sample->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'This sample is '.strtolower($sample->statusLabel()).', so that action is not available. Reload the page to see its current state.']);
        }
    }
}
