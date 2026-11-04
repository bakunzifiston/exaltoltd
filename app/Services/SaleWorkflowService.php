<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class SaleWorkflowService
{
    public function __construct(private readonly PaymentSubmissionService $payments) {}

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $items = $this->normalizeItems($data['items'] ?? []);
            $saleData = $this->buildSaleData($data, $items);

            $sale = Sale::create($saleData);
            $this->createItems($sale, $items);
            $this->syncSaleTotal($sale);
            $this->reconcileLedger($sale, $data);

            return $sale->fresh(['items']);
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            // Once goods have come back against an order its lines are fixed: the returns
            // point at them. Its other details can still be corrected.
            if ($sale->stockReturns()->exists()) {
                $items = $this->normalizeItems($data['items'] ?? []);
                $key = fn ($line) => implode('|', [(int) $line['product_id'], (int) $line['production_id'], round((float) $line['quantity_sold'], 2), round((float) $line['unit_price'], 2)]);
                $current = $sale->items->map(fn ($item) => $key($item->toArray()))->sort()->values()->all();
                if (collect($items)->map($key)->sort()->values()->all() !== $current) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'This order has stock returns recorded against it, so its products and quantities can no longer be changed. Record another return instead.']);
                }
                $sale->update($this->buildSaleData($data, $items, $sale));
                $this->reconcileLedger($sale, $data);

                return $sale->fresh(['items']);
            }

            // Revert old stock deductions before rebuilding items.
            foreach ($sale->items as $oldItem) {
                $production = Production::find($oldItem->production_id);
                if ($production) {
                    $production->quantity_remaining = (float) $production->quantity_remaining + (float) $oldItem->quantity_sold;
                    $production->saveQuietly();

                    StockMovement::record(
                        type: 'sale_reversal',
                        quantity: (float) $oldItem->quantity_sold,
                        productId: $oldItem->product_id,
                        productionId: $oldItem->production_id,
                        referenceType: 'sale_item',
                        referenceId: $oldItem->id,
                        notes: 'Sale ' . $sale->sales_id . ' edited; line restored before rebuild',
                    );
                }
            }

            $sale->items()->delete();

            $items = $this->normalizeItems($data['items'] ?? []);
            $saleData = $this->buildSaleData($data, $items, $sale);

            $sale->update($saleData);
            $this->createItems($sale, $items);
            $this->syncSaleTotal($sale);
            $this->reconcileLedger($sale, $data);

            return $sale->fresh(['items']);
        });
    }

    private function createItems(Sale $sale, array $items): void
    {
        foreach ($items as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'production_id' => $item['production_id'],
                'quantity_sold' => $item['quantity_sold'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ]);
        }
    }

    private function buildSaleData(array $data, array $items, ?Sale $existing = null): array
    {
        $totalRevenue = collect($items)->sum(fn (array $item): float => (float) $item['line_total']);
        $first = $items[0];

        $customer = Customer::resolve(
            name: $data['customer_name'],
            phone: $data['customer_Phone'] ?? null,
            email: $data['customer_email'] ?? null,
            address: $data['delivery_address'] ?? null,
            source: ($data['sales_channel'] ?? '') === 'Online Store' ? 'storefront' : 'admin',
        );

        return [
            'customer_name' => $data['customer_name'],
            'customer_Phone' => $data['customer_Phone'] ?? null,
            'customer_id' => $customer?->id,
            'customer_email' => $data['customer_email'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'barcode' => $data['barcode'] ?? ($data['customer_email'] ?? null),
            'invoice_number' => $data['invoice_number'] ?? null,
            'sale_date' => $data['sale_date'],
            'due_date' => $data['due_date'] ?? null,
            // "Paid" is a claim until an approver confirms it; the ledger decides the status.
            'payment_status' => $data['payment_status'] === 'Paid'
                ? ($existing?->payment_status ?? 'Pending')
                : $data['payment_status'],
            'delivery_status' => $data['delivery_status'],
            'sales_channel' => $data['sales_channel'],
            'product_id' => $first['product_id'] ?? null,
            'production_id' => $first['production_id'] ?? null,
            'quantity_sold' => $first['quantity_sold'] ?? 0,
            'selling_price' => $first['unit_price'] ?? 0,
            'total_revenue' => round($totalRevenue, 2),
        ];
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            $qty = (float) ($item['quantity_sold'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            return [
                'product_id' => $item['product_id'],
                'production_id' => $item['production_id'],
                'quantity_sold' => $qty,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2),
            ];
        })->values()->all();
    }

    private function syncSaleTotal(Sale $sale): void
    {
        $sale->update([
            'total_revenue' => round((float) $sale->items()->sum('line_total'), 2),
        ]);
    }

    /**
     * Marking a sale "Paid" reports the open balance as a payment, with the proof from the
     * form, for approval. Status and amount_paid are then re-derived from the ledger, which
     * only holds approved payments.
     */
    private function reconcileLedger(Sale $sale, array $data): void
    {
        $sale->refresh();

        if (($data['payment_status'] ?? null) === 'Paid') {
            $open = $sale->submittable_balance;
            if ($open > 0) {
                $this->payments->submit($sale, [
                    'amount' => $open,
                    'method' => SalePayment::methodForChannel($sale->sales_channel),
                    'paid_at' => $sale->sale_date,
                    'reference' => $data['payment_reference'] ?? null,
                    'notes' => 'Reported as paid on the sale form',
                ], array_values($data['payment_proofs'] ?? []), auth()->user());
            }
        }

        $sale->refresh()->recalculatePaymentState();
    }
}
