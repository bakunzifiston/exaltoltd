<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleStatusEvent;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records goods a customer sends back against a delivery, and posts them once inspected.
 *
 * Nothing moves until approval. Approval, in one transaction and only from "pending
 * inspection", puts sellable units back on their batch, records unsellable units as
 * received and written off, marks the quantities returned on each sale line, and credits
 * the sale by the value returned. The sale's balance and payment status are then derived
 * again from its payments ledger, so no payment or second sale is ever created.
 */
class StockReturnService
{
    /**
     * What may still come back on each delivered line: delivered less everything already
     * held by another return that is in progress or approved.
     *
     * @return Collection<int, array{item: SaleItem, delivered: float, held: float, returnable: float}>
     */
    public function returnableLines(Sale $sale, ?StockReturn $except = null): Collection
    {
        $held = StockReturnItem::query()
            ->whereHas('stockReturn', fn ($q) => $q->whereIn('status', StockReturn::OPEN_OR_POSTED)
                ->when($except, fn ($q) => $q->whereKeyNot($except->id)))
            ->whereIn('sale_item_id', $sale->items()->select('id'))
            ->groupBy('sale_item_id')
            ->selectRaw('sale_item_id, SUM(quantity_returned) as held')
            ->pluck('held', 'sale_item_id');

        return $sale->items()->with(['product:id,type,name', 'production:id,batch_id'])->get()
            ->map(fn (SaleItem $item) => [
                'item' => $item,
                'delivered' => (float) $item->quantity_sold,
                'held' => round((float) ($held[$item->id] ?? 0), 2),
                'returnable' => max(0, round((float) $item->quantity_sold - (float) ($held[$item->id] ?? 0), 2)),
            ])->keyBy(fn ($line) => $line['item']->id);
    }

    /**
     * Create or update a draft, optionally submitting it for inspection straight away.
     *
     * @param  array{sale_id: int, return_date: string, reason: string, reason_other?: ?string, delivery_note?: ?string, notes?: ?string, lines: array<int, array{sale_item_id: int, quantity_returned: float|string|null, quantity_sold_reported?: float|string|null}>}  $data
     */
    public function save(array $data, User $user, ?StockReturn $return = null, bool $submit = false): StockReturn
    {
        return DB::transaction(function () use ($data, $user, $return, $submit) {
            // Lock the sale so two returns cannot both claim the same units.
            $sale = Sale::query()->lockForUpdate()->findOrFail($data['sale_id']);
            if ($sale->delivery_status !== RevenueRecognitionService::DELIVERED) {
                throw ValidationException::withMessages(['sale_id' => 'Only a delivered order can have stock returned against it. This one is '.strtolower((string) $sale->delivery_status).'.']);
            }
            if ($return && ! $return->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Only a draft return can be edited.']);
            }

            $lines = $this->validatedLines($sale, $data['lines'] ?? [], $return);

            $return ??= (new StockReturn)->forceFill(['status' => StockReturn::DRAFT, 'created_by' => $user->id]);
            $return->fill([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer_name,
                'delivery_note' => ($data['delivery_note'] ?? null) ?: ($sale->invoice_number ?: $sale->sales_id),
                'return_date' => $data['return_date'],
                'reason' => $data['reason'],
                'reason_other' => $data['reason'] === 'other' ? ($data['reason_other'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);
            $return->total_value = round($lines->sum('line_value'), 2);
            $return->save();

            $return->items()->delete();
            $return->items()->createMany($lines->all());

            if ($submit) {
                $this->submit($return, $user);
            }

            return $return->fresh(['items']);
        });
    }

    public function submit(StockReturn $return, User $user): void
    {
        DB::transaction(function () use ($return, $user) {
            $return = StockReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->expect($return, StockReturn::DRAFT);
            $return->forceFill(['status' => StockReturn::PENDING_INSPECTION, 'submitted_by' => $user->id, 'submitted_at' => now()])->save();
        });
    }

    /**
     * Post an inspected return. Each line's returned quantity must be split exactly into
     * sellable (restocked) and unsellable (damaged, expired, rejected) units.
     *
     * @param  array<int, array{quantity_restocked?: mixed, quantity_damaged?: mixed, quantity_expired?: mixed, quantity_rejected?: mixed, condition_notes?: ?string}>  $inspection  keyed by return item id
     */
    public function approve(StockReturn $return, array $inspection, User $user, ?string $notes = null): StockReturn
    {
        return DB::transaction(function () use ($return, $inspection, $user, $notes) {
            $return = StockReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->expect($return, StockReturn::PENDING_INSPECTION);
            $sale = Sale::query()->lockForUpdate()->findOrFail($return->sale_id);

            // Quantities are checked again under lock: another return may have been
            // approved since this one was submitted.
            $returnable = $this->returnableLines($sale, $return);
            $errors = [];

            foreach ($return->items as $line) {
                $split = $inspection[$line->id] ?? [];
                $parts = array_map(fn ($k) => round(max(0, (float) ($split[$k] ?? 0)), 2), ['restocked' => 'quantity_restocked', 'damaged' => 'quantity_damaged', 'expired' => 'quantity_expired', 'rejected' => 'quantity_rejected']);
                if (abs(array_sum($parts) - (float) $line->quantity_returned) >= 0.005) {
                    $errors["inspection.{$line->id}"] = 'Line '.($line->product?->type ?? '#'.$line->id).': sellable, damaged, expired and rejected must add up to the '.(float) $line->quantity_returned.' returned.';
                }
                if ((float) $line->quantity_returned > ($returnable[$line->sale_item_id]['returnable'] ?? 0) + 0.0001) {
                    $errors["inspection.{$line->id}"] = 'Line '.($line->product?->type ?? '#'.$line->id).': more is being returned than is still outstanding on the delivery.';
                }
                $line->fill(['quantity_restocked' => $parts['restocked'], 'quantity_damaged' => $parts['damaged'], 'quantity_expired' => $parts['expired'], 'quantity_rejected' => $parts['rejected'], 'condition_notes' => $split['condition_notes'] ?? null]);
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($return->items as $line) {
                $line->save();
                $this->postLine($return, $line);
            }

            $sale->forceFill(['returned_amount' => round((float) $sale->returned_amount + (float) $return->total_value, 2)]);
            $sale->recalculatePaymentState();
            SaleStatusEvent::create(['sale_id' => $sale->id, 'field' => 'stock_return', 'from_status' => null, 'to_status' => $return->reference, 'changed_by' => $user->id]);

            $return->forceFill(['status' => StockReturn::APPROVED, 'decided_by' => $user->id, 'decided_at' => now(), 'inspection_notes' => $notes])->save();

            return $return->fresh(['items']);
        });
    }

    public function reject(StockReturn $return, User $user, string $notes): void
    {
        DB::transaction(function () use ($return, $user, $notes) {
            $return = StockReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->expect($return, StockReturn::PENDING_INSPECTION);
            $return->forceFill(['status' => StockReturn::REJECTED, 'decided_by' => $user->id, 'decided_at' => now(), 'inspection_notes' => $notes])->save();
        });
    }

    public function cancel(StockReturn $return, User $user, string $notes): void
    {
        DB::transaction(function () use ($return, $user, $notes) {
            $return = StockReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->expect($return, StockReturn::DRAFT, StockReturn::PENDING_INSPECTION);
            $return->forceFill(['status' => StockReturn::CANCELLED, 'decided_by' => $user->id, 'decided_at' => now(), 'inspection_notes' => $notes])->save();
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function validatedLines(Sale $sale, array $input, ?StockReturn $return): Collection
    {
        $returnable = $this->returnableLines($sale, $return);
        $errors = [];
        $lines = collect();
        $seen = [];

        foreach ($input as $i => $row) {
            $itemId = (int) ($row['sale_item_id'] ?? 0);
            $returned = round((float) ($row['quantity_returned'] ?? 0), 2);
            $sold = round((float) ($row['quantity_sold_reported'] ?? 0), 2);
            if ($returned <= 0 && $sold <= 0) {
                continue;
            }
            $line = $returnable[$itemId] ?? null;
            if (! $line) {
                $errors["lines.$i.sale_item_id"] = 'That product line is not on the selected order.';

                continue;
            }
            if (isset($seen[$itemId])) {
                $errors["lines.$i.sale_item_id"] = 'Each delivered line can appear only once in a return.';

                continue;
            }
            $seen[$itemId] = true;
            $name = $line['item']->product?->type ?? 'line '.$itemId;

            if ($returned < 0 || $sold < 0) {
                $errors["lines.$i.quantity_returned"] = $name.': quantities cannot be negative.';
            } elseif ($returned > $line['returnable'] + 0.0001) {
                $errors["lines.$i.quantity_returned"] = sprintf('%s: only %s can still be returned (%s delivered, %s already on other returns).', $name, $this->qty($line['returnable']), $this->qty($line['delivered']), $this->qty($line['held']));
            } elseif ($returned + $sold > $line['returnable'] + 0.0001) {
                $errors["lines.$i.quantity_sold_reported"] = sprintf('%s: sold (%s) plus returned (%s) is more than the %s still outstanding.', $name, $this->qty($sold), $this->qty($returned), $this->qty($line['returnable']));
            }

            if ($returned > 0) {
                $lines->push([
                    'sale_item_id' => $itemId,
                    'product_id' => $line['item']->product_id,
                    'production_id' => $line['item']->production_id,
                    'quantity_delivered' => $line['delivered'],
                    'quantity_sold_reported' => $sold,
                    'quantity_returned' => $returned,
                    'unit_price' => (float) $line['item']->unit_price,
                    'line_value' => round($returned * (float) $line['item']->unit_price, 2),
                ]);
            }
        }

        if ($errors === [] && $lines->isEmpty()) {
            $errors['lines'] = 'Enter a returned quantity on at least one product.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    private function postLine(StockReturn $return, StockReturnItem $line): void
    {
        $saleItem = SaleItem::query()->lockForUpdate()->findOrFail($line->sale_item_id);
        $saleItem->forceFill(['quantity_returned' => round((float) $saleItem->quantity_returned + (float) $line->quantity_returned, 2)])->saveQuietly();

        $restocked = (float) $line->quantity_restocked;
        if ($restocked > 0 && $line->production_id) {
            $batch = Production::query()->lockForUpdate()->findOrFail($line->production_id);
            $batch->quantity_remaining = round((float) $batch->quantity_remaining + $restocked, 2);
            $batch->save();
        }

        $this->move('return_restock', $restocked, $line, $return, 'Returned and passed inspection; back in sellable stock');

        // Unsellable units are recorded coming in and going straight out, so the movement
        // history shows them without ever adding them to what can be sold.
        $unsellable = $line->unsellableQuantity();
        $why = collect(['damaged' => $line->quantity_damaged, 'expired' => $line->quantity_expired, 'rejected' => $line->quantity_rejected])
            ->filter(fn ($q) => (float) $q > 0)->map(fn ($q, $k) => (float) $q.' '.$k)->implode(', ');
        $this->move('return_received', $unsellable, $line, $return, 'Returned unsellable ('.$why.')');
        $this->move('return_written_off', -1 * $unsellable, $line, $return, 'Unsellable return written off ('.$why.')');
    }

    private function move(string $type, float $quantity, StockReturnItem $line, StockReturn $return, string $notes): void
    {
        if (abs($quantity) < 0.0001) {
            return;
        }
        StockMovement::record(
            type: $type,
            quantity: $quantity,
            productId: $line->product_id,
            productionId: $line->production_id,
            referenceType: 'stock_return',
            referenceId: $return->id,
            notes: $return->reference.': '.$notes,
        );
    }

    private function expect(StockReturn $return, string ...$statuses): void
    {
        if (! in_array($return->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'This return is '.strtolower($return->statusLabel()).', so that action is not available. Reload the page to see its current state.']);
        }
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
