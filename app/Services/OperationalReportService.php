<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Operational reports: raw materials, sales and production.
 *
 * Each report is described once as data — detail sheets of rows, headline metrics and
 * breakdown tables grouped over those rows — and summarise() derives every figure from
 * the detail rows. The on-screen preview and the Excel download both read that one
 * summary, so they cannot disagree, and every headline can be traced to a detail row.
 *
 * Layout follows the Raw Material Purchase & Cost of Goods workbook the business already
 * uses: a Summary sheet (headline, breakdowns, how to read) followed by the detail sheets.
 */
class OperationalReportService
{
    public const TYPES = [
        'raw-materials' => 'Raw materials',
        'sales' => 'Sales',
        'production' => 'Production',
        'returns' => 'Stock returns',
        'samples' => 'Samples',
        'customer-balances' => 'Customer balances',
        'stock-movements' => 'Stock movements',
    ];

    private const TITLES = [
        'raw-materials' => 'Raw Material Purchase & Cost of Goods Report',
        'sales' => 'Sales Report',
        'production' => 'Production Report',
        'returns' => 'Stock Returns Report',
        'samples' => 'Production Samples Report',
        'customer-balances' => 'Customer Balances Report',
        'stock-movements' => 'Finished Goods Movements Report',
    ];

    public function __construct(private readonly ProductionCostingService $costing) {}

    public function build(string $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $report = match ($type) {
            'raw-materials' => $this->rawMaterials($from, $to),
            'sales' => $this->sales($from, $to),
            'production' => $this->production($from, $to),
            'returns' => $this->returns($from, $to),
            'samples' => $this->samples($from, $to),
            'customer-balances' => $this->customerBalances($to),
            'stock-movements' => $this->stockMovements($from, $to),
        };

        return $this->summarise($report + [
            'type' => $type,
            'title' => self::TITLES[$type],
            'subtitle' => 'Exalto Green Ltd  ·  '.$from->format('d M Y').' – '.$to->format('d M Y').'  ·  Amounts in RWF  ·  Prepared '.now()->format('d M Y'),
            'filename' => 'shaky-'.$type.'-report-'.$from->toDateString().'-to-'.$to->toDateString().'.xlsx',
        ]);
    }

    // ── Report definitions ──────────────────────────────────────────────────

    private function rawMaterials(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Usage of each lot up to the end of the period, whenever it was bought, so a
        // purchase's "used" and "in stock" split reflects every batch that drew on it.
        $usedByLot = [];
        foreach ($this->costing->consumption(null, $to->toDateString()) as $line) {
            $usedByLot[$line['inventory_id']] = ($usedByLot[$line['inventory_id']] ?? 0) + $line['quantity'];
        }

        $purchases = [];
        foreach (InventoryRecord::query()
            ->whereDate('record_date', '>=', $from->toDateString())
            ->whereDate('record_date', '<=', $to->toDateString())
            ->orderBy('record_date')->orderBy('id')->get() as $record) {
            $qty = (float) $record->quantity_in;
            $amount = $record->total_amount !== null ? (float) $record->total_amount : null;
            $unit = (float) ($record->unit_cost ?? 0);
            if ($unit <= 0) {
                $unit = $qty > 0 && $amount > 0 ? $amount / $qty : 0.0;
            }
            $amount ??= round($qty * $unit, 2);
            $used = round((float) ($usedByLot[$record->id] ?? 0), 2);
            $damaged = (float) $record->damaged;
            $onHand = max(0, round($qty - $used - $damaged, 2));
            $paid = min((float) $record->amount_paid, $amount);

            $flags = [];
            if ($unit <= 0) {
                $flags[] = 'No price - usage cannot be costed';
            } elseif (abs($amount - $qty * $unit) >= 1) {
                $flags[] = 'Amount differs from qty x unit cost';
            }
            if ($used > $qty + 0.001) {
                $flags[] = 'More used than purchased';
            }

            $purchases[] = [
                'id' => $record->id,
                'date' => $record->record_date->toDateString(),
                'supplier' => trim((string) $record->supplier_name),
                'item' => trim((string) $record->item_name),
                'item_type' => (string) $record->item_type,
                'invoice' => (string) $record->invoice_number,
                'qty' => $qty,
                'unit_cost' => $unit > 0 ? round($unit, 2) : null,
                'amount' => $amount,
                'paid' => $paid,
                'outstanding' => round($amount - $paid, 2),
                'payment_status' => (string) $record->payment_status,
                'qty_used' => $used,
                'cogs_used' => round($used * $unit, 2),
                'damaged' => $damaged,
                'qty_on_hand' => $onHand,
                'stock_value' => round($onHand * $unit, 2),
                'month' => $record->record_date->format('Y-m'),
                'flag' => implode('; ', $flags),
            ];
        }

        $usage = $this->usageRows($from, $to);

        return [
            'sheets' => [
                'purchases' => ['name' => 'Purchases', 'rows' => $purchases, 'columns' => [
                    'id' => ['Record ID', 'int', 11], 'date' => ['Purchase Date', 'date', 14], 'supplier' => ['Supplier', 'text', 30],
                    'item' => ['Item', 'text', 28], 'item_type' => ['Item Type', 'text', 14], 'invoice' => ['Invoice', 'text', 14],
                    'qty' => ['Qty Purchased', 'qty', 14], 'unit_cost' => ['Unit Cost (RWF)', 'money', 15], 'amount' => ['Purchase Amount (RWF)', 'money', 20],
                    'paid' => ['Amount Paid (RWF)', 'money', 18], 'outstanding' => ['Outstanding (RWF)', 'money', 17], 'payment_status' => ['Payment Status', 'text', 15],
                    'qty_used' => ['Qty Used in Production', 'qty', 20], 'cogs_used' => ['Cost of Goods Used (RWF)', 'money', 22], 'damaged' => ['Qty Damaged', 'qty', 12],
                    'qty_on_hand' => ['Qty On Hand', 'qty', 13], 'stock_value' => ['Stock Value On Hand (RWF)', 'money', 23], 'month' => ['Month', 'text', 10],
                    'flag' => ['Review Flag', 'text', 40],
                ]],
                'usage' => $usage,
            ],
            'headline' => [
                ['key' => 'purchases', 'label' => 'Total raw material purchases', 'note' => 'purchases dated in the period', 'kind' => 'sum', 'sheet' => 'purchases', 'column' => 'amount', 'format' => 'money'],
                ['key' => 'consumed', 'label' => 'Cost of goods – raw materials used', 'note' => 'consumed by batches made in the period', 'kind' => 'sum', 'sheet' => 'usage', 'column' => 'cost', 'format' => 'money', 'emphasis' => true],
                ['key' => 'used', 'label' => 'Of these purchases, already used', 'note' => 'drawn into production to the end of the period', 'kind' => 'sum', 'sheet' => 'purchases', 'column' => 'cogs_used', 'format' => 'money'],
                ['key' => 'stock', 'label' => 'Raw materials still in stock (value)', 'note' => 'purchased in the period, not yet used', 'kind' => 'sum', 'sheet' => 'purchases', 'column' => 'stock_value', 'format' => 'money'],
                ['key' => 'share', 'label' => 'Share of purchases already used', 'kind' => 'ratio', 'num' => 'used', 'den' => 'purchases', 'format' => 'pct'],
                ['key' => 'paid', 'label' => 'Amount paid to suppliers', 'kind' => 'sum', 'sheet' => 'purchases', 'column' => 'paid', 'format' => 'money'],
                ['key' => 'outstanding', 'label' => 'Outstanding to suppliers', 'kind' => 'sum', 'sheet' => 'purchases', 'column' => 'outstanding', 'format' => 'money'],
                ['key' => 'records', 'label' => 'Purchase records in report', 'kind' => 'count', 'sheet' => 'purchases', 'format' => 'int'],
                ['key' => 'flagged', 'label' => 'Records flagged for review', 'note' => 'see Review Flag on Purchases', 'kind' => 'count', 'sheet' => 'purchases', 'where' => ['flag' => '!'], 'format' => 'int'],
            ],
            'tables' => [
                ['title' => 'By item', 'sheet' => 'purchases', 'group' => 'item', 'group_label' => 'Item', 'sort' => 'purchases', 'measures' => [
                    ['key' => 'purchases', 'label' => 'Purchases', 'kind' => 'sum', 'column' => 'amount', 'format' => 'money'],
                    ['key' => 'used', 'label' => 'Cost of Goods Used', 'kind' => 'sum', 'column' => 'cogs_used', 'format' => 'money'],
                    ['key' => 'stock', 'label' => 'In Stock', 'kind' => 'sum', 'column' => 'stock_value', 'format' => 'money'],
                    ['key' => 'spend_share', 'label' => '% of Spend', 'kind' => 'share', 'of' => 'purchases', 'format' => 'pct'],
                    ['key' => 'used_share', 'label' => '% of Used', 'kind' => 'share', 'of' => 'used', 'format' => 'pct'],
                ]],
                ['title' => 'By supplier', 'sheet' => 'purchases', 'group' => 'supplier', 'group_label' => 'Supplier', 'sort' => 'purchases', 'measures' => [
                    ['key' => 'purchases', 'label' => 'Purchases', 'kind' => 'sum', 'column' => 'amount', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                    ['key' => 'outstanding', 'label' => 'Outstanding', 'kind' => 'sum', 'column' => 'outstanding', 'format' => 'money'],
                    ['key' => 'spend_share', 'label' => '% of Spend', 'kind' => 'share', 'of' => 'purchases', 'format' => 'pct'],
                ]],
                ['title' => 'Purchases by month', 'sheet' => 'purchases', 'group' => 'month', 'group_label' => 'Month', 'sort' => 'label', 'measures' => [
                    ['key' => 'purchases', 'label' => 'Purchases', 'kind' => 'sum', 'column' => 'amount', 'format' => 'money'],
                    ['key' => 'used', 'label' => 'Cost of Goods Used', 'kind' => 'sum', 'column' => 'cogs_used', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                ]],
                ['title' => 'Raw materials consumed in production, by item', 'sheet' => 'usage', 'group' => 'item', 'group_label' => 'Item', 'sort' => 'cost', 'measures' => [
                    ['key' => 'qty', 'label' => 'Qty Used', 'kind' => 'sum', 'column' => 'qty', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost of Goods', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Cost of Goods', 'kind' => 'share', 'of' => 'cost', 'format' => 'pct'],
                ]],
                ['title' => 'Cost of goods by production batch', 'sheet' => 'usage', 'group' => 'batch', 'group_label' => 'Batch', 'sort' => 'date', 'measures' => [
                    ['key' => 'date', 'label' => 'Batch Date', 'kind' => 'first', 'column' => 'date', 'format' => 'date'],
                    ['key' => 'product', 'label' => 'Product', 'kind' => 'first', 'column' => 'product', 'format' => 'text'],
                    ['key' => 'cost', 'label' => 'Raw Material Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
            ],
            'notes' => [
                'Purchases = what was bought in the period (invoice totals). Cost of goods – raw materials used = the quantities production batches made in the period drew from stock, valued at each lot\'s purchase price. This is the figure the income statement charges as cost of goods.',
                'For each purchase, "used" counts every batch that drew on it up to the end of the period; in stock = quantity purchased less used and damaged, at the lot\'s unit cost.',
                'Purchases are stock, not an expense, until production uses them.',
                'Rows with a Review Flag need a quick check: a missing price, an amount that does not equal quantity x unit cost, or more used than was bought.',
            ],
        ];
    }

    private function sales(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = [];
        foreach (Sale::query()
            ->whereDate('sale_date', '>=', $from->toDateString())
            ->whereDate('sale_date', '<=', $to->toDateString())
            ->orderBy('sale_date')->orderBy('id')->get() as $sale) {
            $value = (float) $sale->total_revenue;
            $paid = (float) $sale->amount_paid;
            $date = CarbonImmutable::parse($sale->sale_date);
            $sales[] = [
                'sale' => (string) $sale->sales_id,
                'date' => $date->toDateString(),
                'invoice' => (string) $sale->invoice_number,
                'customer' => trim((string) $sale->customer_name),
                'channel' => (string) ($sale->sales_channel ?: 'Unspecified'),
                'delivery' => (string) $sale->delivery_status,
                'payment' => (string) $sale->payment_status,
                'value' => $value,
                'paid' => $paid,
                'balance' => round(max(0, $value - $paid), 2),
                'due_date' => $sale->due_date?->toDateString(),
                'month' => $date->format('Y-m'),
            ];
        }

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('productions', 'productions.id', '=', 'sale_items.production_id')
            ->whereDate('sales.sale_date', '>=', $from->toDateString())
            ->whereDate('sales.sale_date', '<=', $to->toDateString())
            ->orderBy('sales.sale_date')->orderBy('sale_items.id')
            ->get([
                'sale_items.production_id', 'sale_items.quantity_sold', 'sale_items.unit_price', 'sale_items.line_total', 'sale_items.line_cogs',
                'sales.sales_id', 'sales.sale_date', 'sales.customer_name', 'sales.sales_channel', 'sales.delivery_status',
                'productions.batch_id', DB::raw('COALESCE(products.type, products.name) as product_label'),
            ]);
        $unitCosts = $this->costing->unitCostsFor($items->pluck('production_id')->unique()->all());

        $lines = [];
        foreach ($items as $item) {
            $qty = (float) $item->quantity_sold;
            $revenue = (float) $item->line_total;
            $batchUnit = $unitCosts[$item->production_id] ?? null;
            // Same priority as SaleCostingService: the batch cost, else the cost frozen on the line.
            $cogs = $batchUnit !== null ? round($batchUnit * $qty, 2) : ($item->line_cogs !== null ? (float) $item->line_cogs : null);
            $date = CarbonImmutable::parse($item->sale_date);
            $lines[] = [
                'sale' => (string) $item->sales_id,
                'date' => $date->toDateString(),
                'customer' => trim((string) $item->customer_name),
                'channel' => (string) ($item->sales_channel ?: 'Unspecified'),
                'product' => (string) ($item->product_label ?? 'Unspecified product'),
                'batch' => (string) $item->batch_id,
                'qty' => $qty,
                'unit_price' => (float) $item->unit_price,
                'revenue' => $revenue,
                'unit_cost' => $cogs !== null && $qty > 0 ? round($cogs / $qty, 2) : null,
                'cogs' => $cogs,
                'profit' => $cogs !== null ? round($revenue - $cogs, 2) : null,
                'delivery' => (string) $item->delivery_status,
                'month' => $date->format('Y-m'),
            ];
        }

        return [
            'sheets' => [
                'sales' => ['name' => 'Sales', 'rows' => $sales, 'columns' => [
                    'sale' => ['Sale ID', 'text', 16], 'date' => ['Sale Date', 'date', 13], 'invoice' => ['Invoice', 'text', 14],
                    'customer' => ['Customer', 'text', 30], 'channel' => ['Channel', 'text', 14], 'delivery' => ['Delivery Status', 'text', 15],
                    'payment' => ['Payment Status', 'text', 15], 'value' => ['Sale Value (RWF)', 'money', 17], 'paid' => ['Amount Paid (RWF)', 'money', 18],
                    'balance' => ['Balance Due (RWF)', 'money', 18], 'due_date' => ['Due Date', 'date', 13], 'month' => ['Month', 'text', 10],
                ]],
                'lines' => ['name' => 'Sale Lines', 'rows' => $lines, 'columns' => [
                    'sale' => ['Sale ID', 'text', 16], 'date' => ['Sale Date', 'date', 13], 'customer' => ['Customer', 'text', 28],
                    'channel' => ['Channel', 'text', 14], 'product' => ['Product', 'text', 28], 'batch' => ['Batch', 'text', 14],
                    'qty' => ['Qty Sold', 'qty', 11], 'unit_price' => ['Unit Price (RWF)', 'money', 15], 'revenue' => ['Revenue (RWF)', 'money', 15],
                    'unit_cost' => ['Unit Cost (RWF)', 'money', 15], 'cogs' => ['Cost of Goods (RWF)', 'money', 18], 'profit' => ['Gross Profit (RWF)', 'money', 17],
                    'delivery' => ['Delivery Status', 'text', 15], 'month' => ['Month', 'text', 10],
                ]],
            ],
            'headline' => [
                ['key' => 'value', 'label' => 'Total sales value', 'note' => 'all orders dated in the period', 'kind' => 'sum', 'sheet' => 'sales', 'column' => 'value', 'format' => 'money'],
                ['key' => 'delivered', 'label' => 'Delivered sales (revenue recognised)', 'note' => 'what the income statement counts', 'kind' => 'sum', 'sheet' => 'sales', 'column' => 'value', 'where' => ['delivery' => RevenueRecognitionService::DELIVERED], 'format' => 'money', 'emphasis' => true],
                ['key' => 'paid', 'label' => 'Cash collected on these sales', 'kind' => 'sum', 'sheet' => 'sales', 'column' => 'paid', 'format' => 'money'],
                ['key' => 'balance', 'label' => 'Outstanding balance due', 'kind' => 'sum', 'sheet' => 'sales', 'column' => 'balance', 'format' => 'money'],
                ['key' => 'count', 'label' => 'Number of sales', 'kind' => 'count', 'sheet' => 'sales', 'format' => 'int'],
                ['key' => 'units', 'label' => 'Units sold', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'qty', 'format' => 'qty'],
                ['key' => 'revenue', 'label' => 'Revenue on item lines', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'revenue', 'format' => 'money'],
                ['key' => 'cogs', 'label' => 'Cost of goods of items sold', 'note' => 'batch raw-material cost per unit', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'cogs', 'format' => 'money'],
                ['key' => 'profit', 'label' => 'Gross profit', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'profit', 'format' => 'money'],
                ['key' => 'margin', 'label' => 'Gross margin', 'kind' => 'ratio', 'num' => 'profit', 'den' => 'revenue', 'format' => 'pct'],
            ],
            'tables' => [
                ['title' => 'By product', 'sheet' => 'lines', 'group' => 'product', 'group_label' => 'Product', 'sort' => 'revenue', 'measures' => [
                    ['key' => 'qty', 'label' => 'Qty Sold', 'kind' => 'sum', 'column' => 'qty', 'format' => 'qty'],
                    ['key' => 'revenue', 'label' => 'Revenue', 'kind' => 'sum', 'column' => 'revenue', 'format' => 'money'],
                    ['key' => 'cogs', 'label' => 'Cost of Goods', 'kind' => 'sum', 'column' => 'cogs', 'format' => 'money'],
                    ['key' => 'profit', 'label' => 'Gross Profit', 'kind' => 'sum', 'column' => 'profit', 'format' => 'money'],
                    ['key' => 'margin', 'label' => 'Margin', 'kind' => 'ratio', 'num' => 'profit', 'den' => 'revenue', 'format' => 'pct'],
                    ['key' => 'share', 'label' => '% of Revenue', 'kind' => 'share', 'of' => 'revenue', 'format' => 'pct'],
                ]],
                ['title' => 'By customer', 'sheet' => 'sales', 'group' => 'customer', 'group_label' => 'Customer', 'sort' => 'value', 'measures' => [
                    ['key' => 'count', 'label' => 'Sales', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'value', 'label' => 'Sale Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                    ['key' => 'balance', 'label' => 'Balance Due', 'kind' => 'sum', 'column' => 'balance', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Sales', 'kind' => 'share', 'of' => 'value', 'format' => 'pct'],
                ]],
                ['title' => 'By sales channel', 'sheet' => 'sales', 'group' => 'channel', 'group_label' => 'Channel', 'sort' => 'value', 'measures' => [
                    ['key' => 'count', 'label' => 'Sales', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'value', 'label' => 'Sale Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Sales', 'kind' => 'share', 'of' => 'value', 'format' => 'pct'],
                ]],
                ['title' => 'Sales by month', 'sheet' => 'sales', 'group' => 'month', 'group_label' => 'Month', 'sort' => 'label', 'measures' => [
                    ['key' => 'count', 'label' => 'Sales', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'value', 'label' => 'Sale Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                    ['key' => 'balance', 'label' => 'Balance Due', 'kind' => 'sum', 'column' => 'balance', 'format' => 'money'],
                ]],
                ['title' => 'By payment status', 'sheet' => 'sales', 'group' => 'payment', 'group_label' => 'Payment Status', 'sort' => 'value', 'measures' => [
                    ['key' => 'count', 'label' => 'Sales', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'value', 'label' => 'Sale Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'balance', 'label' => 'Balance Due', 'kind' => 'sum', 'column' => 'balance', 'format' => 'money'],
                ]],
                ['title' => 'By delivery status', 'sheet' => 'sales', 'group' => 'delivery', 'group_label' => 'Delivery Status', 'sort' => 'value', 'measures' => [
                    ['key' => 'count', 'label' => 'Sales', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'value', 'label' => 'Sale Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Sales', 'kind' => 'share', 'of' => 'value', 'format' => 'pct'],
                ]],
            ],
            'notes' => [
                'Sales covers every order dated in the period, whatever its status. Revenue is recognised only once an order is delivered, so the delivered figure is the one that matches the income statement.',
                'Cost of goods on each line is the raw-material cost per unit of the batch it came from (materials consumed / units produced). Lines from a batch with no priced materials show no cost and no profit.',
                'Cash collected and balance due are the amounts recorded against these sales to date, whenever they were received.',
            ],
        ];
    }

    private function production(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $batches = Production::query()
            ->with('product:id,name,type')
            ->whereDate('production_date', '>=', $from->toDateString())
            ->whereDate('production_date', '<=', $to->toDateString())
            ->orderBy('production_date')->orderBy('id')->get();

        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sale_items.production_id', $batches->pluck('id'))
            ->whereDate('sales.sale_date', '<=', $to->toDateString())
            ->where('sales.delivery_status', '!=', 'Returned')
            ->groupBy('sale_items.production_id')
            ->select('sale_items.production_id', DB::raw('SUM(sale_items.quantity_sold) as sold_qty'))
            ->pluck('sold_qty', 'production_id');

        $rows = [];
        foreach ($batches as $batch) {
            $produced = (float) $batch->quantity_produced;
            $damaged = (float) $batch->damaged;
            $cost = $this->costing->batchCost($batch);
            $unit = $this->costing->unitCost($batch);
            $remaining = (float) $batch->quantity_remaining;
            $date = CarbonImmutable::parse($batch->production_date);

            $flag = match (true) {
                $this->costing->materials($batch) === [] => 'No raw materials recorded',
                $cost === null => 'Raw materials have no purchase price',
                default => '',
            };

            $rows[] = [
                'batch' => (string) $batch->batch_id,
                'date' => $date->toDateString(),
                'product' => (string) ($batch->product?->type ?: $batch->product?->name ?: 'Unspecified product'),
                'staff' => (string) ($batch->responsible_staff ?: 'Unassigned'),
                'produced' => $produced,
                'damaged' => $damaged,
                'good' => max(0, $produced - $damaged),
                'sold' => round((float) ($sold[$batch->id] ?? 0), 2),
                'remaining' => $remaining,
                'cost' => $cost,
                'unit_cost' => $unit !== null ? round($unit, 2) : null,
                'stock_value' => $unit !== null ? round($remaining * $unit, 2) : null,
                'month' => $date->format('Y-m'),
                'notes' => (string) $batch->quality_control_notes,
                'flag' => $flag,
            ];
        }

        return [
            'sheets' => [
                'batches' => ['name' => 'Batches', 'rows' => $rows, 'columns' => [
                    'batch' => ['Batch', 'text', 16], 'date' => ['Production Date', 'date', 15], 'product' => ['Product', 'text', 28],
                    'staff' => ['Responsible Staff', 'text', 22], 'produced' => ['Qty Produced', 'qty', 13], 'damaged' => ['Qty Damaged', 'qty', 12],
                    'good' => ['Good Units', 'qty', 12], 'sold' => ['Units Sold to Date', 'qty', 17], 'remaining' => ['Units Remaining', 'qty', 15],
                    'cost' => ['Raw Material Cost (RWF)', 'money', 22], 'unit_cost' => ['Cost per Unit (RWF)', 'money', 18], 'stock_value' => ['Remaining Stock Value (RWF)', 'money', 25],
                    'month' => ['Month', 'text', 10], 'notes' => ['Quality Control Notes', 'text', 40], 'flag' => ['Review Flag', 'text', 34],
                ]],
                'usage' => $this->usageRows($from, $to, 'Materials Used'),
            ],
            'headline' => [
                ['key' => 'batches', 'label' => 'Production batches', 'kind' => 'count', 'sheet' => 'batches', 'format' => 'int'],
                ['key' => 'produced', 'label' => 'Units produced', 'kind' => 'sum', 'sheet' => 'batches', 'column' => 'produced', 'format' => 'qty'],
                ['key' => 'damaged', 'label' => 'Units damaged', 'kind' => 'sum', 'sheet' => 'batches', 'column' => 'damaged', 'format' => 'qty'],
                ['key' => 'damage_rate', 'label' => 'Damage rate', 'kind' => 'ratio', 'num' => 'damaged', 'den' => 'produced', 'format' => 'pct'],
                ['key' => 'sold', 'label' => 'Units sold to date', 'note' => 'from these batches, to the end of the period', 'kind' => 'sum', 'sheet' => 'batches', 'column' => 'sold', 'format' => 'qty'],
                ['key' => 'remaining', 'label' => 'Units remaining in stock', 'note' => 'current balance on each batch', 'kind' => 'sum', 'sheet' => 'batches', 'column' => 'remaining', 'format' => 'qty'],
                ['key' => 'cost', 'label' => 'Raw materials consumed (cost)', 'note' => 'charged to cost of goods', 'kind' => 'sum', 'sheet' => 'usage', 'column' => 'cost', 'format' => 'money', 'emphasis' => true],
                ['key' => 'costed', 'label' => 'Units from costed batches', 'kind' => 'sum', 'sheet' => 'batches', 'column' => 'produced', 'where' => ['flag' => ''], 'format' => 'qty'],
                ['key' => 'avg_unit', 'label' => 'Average raw-material cost per unit', 'note' => 'over batches with priced materials', 'kind' => 'ratio', 'num' => 'cost', 'den' => 'costed', 'format' => 'money'],
                ['key' => 'flagged', 'label' => 'Batches flagged for review', 'note' => 'see Review Flag on Batches', 'kind' => 'count', 'sheet' => 'batches', 'where' => ['flag' => '!'], 'format' => 'int'],
            ],
            'tables' => [
                ['title' => 'By product', 'sheet' => 'batches', 'group' => 'product', 'group_label' => 'Product', 'sort' => 'produced', 'measures' => [
                    ['key' => 'count', 'label' => 'Batches', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'produced', 'label' => 'Produced', 'kind' => 'sum', 'column' => 'produced', 'format' => 'qty'],
                    ['key' => 'damaged', 'label' => 'Damaged', 'kind' => 'sum', 'column' => 'damaged', 'format' => 'qty'],
                    ['key' => 'sold', 'label' => 'Sold', 'kind' => 'sum', 'column' => 'sold', 'format' => 'qty'],
                    ['key' => 'remaining', 'label' => 'Remaining', 'kind' => 'sum', 'column' => 'remaining', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Material Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Cost', 'kind' => 'share', 'of' => 'cost', 'format' => 'pct'],
                ]],
                ['title' => 'Production by month', 'sheet' => 'batches', 'group' => 'month', 'group_label' => 'Month', 'sort' => 'label', 'measures' => [
                    ['key' => 'count', 'label' => 'Batches', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'produced', 'label' => 'Produced', 'kind' => 'sum', 'column' => 'produced', 'format' => 'qty'],
                    ['key' => 'damaged', 'label' => 'Damaged', 'kind' => 'sum', 'column' => 'damaged', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Material Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
                ['title' => 'Raw materials consumed, by item', 'sheet' => 'usage', 'group' => 'item', 'group_label' => 'Item', 'sort' => 'cost', 'measures' => [
                    ['key' => 'qty', 'label' => 'Qty Used', 'kind' => 'sum', 'column' => 'qty', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost of Goods', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Cost', 'kind' => 'share', 'of' => 'cost', 'format' => 'pct'],
                ]],
                ['title' => 'By responsible staff', 'sheet' => 'batches', 'group' => 'staff', 'group_label' => 'Staff', 'sort' => 'produced', 'measures' => [
                    ['key' => 'count', 'label' => 'Batches', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'produced', 'label' => 'Produced', 'kind' => 'sum', 'column' => 'produced', 'format' => 'qty'],
                    ['key' => 'damaged', 'label' => 'Damaged', 'kind' => 'sum', 'column' => 'damaged', 'format' => 'qty'],
                    ['key' => 'damage_rate', 'label' => 'Damage Rate', 'kind' => 'ratio', 'num' => 'damaged', 'den' => 'produced', 'format' => 'pct'],
                ]],
            ],
            'notes' => [
                'Covers batches with a production date in the period. Raw material cost is each material drawn into the batch valued at its lot\'s purchase price; cost per unit divides that by units produced.',
                'Units sold to date counts sale lines from each batch up to the end of the period, excluding returns. Units remaining is the batch\'s current stock balance.',
                'Batches flagged "No raw materials recorded" or "no purchase price" carry no cost, so anything sold from them shows no cost of goods. Record their materials to correct this.',
            ],
        ];
    }

    private function returns(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $returns = StockReturn::query()->with(['sale:id,sales_id', 'decider:id,name'])
            ->withSum('items as units', 'quantity_returned')
            ->whereBetween('return_date', DateBounds::between($from, $to))
            ->orderBy('return_date')->orderBy('id')->get();

        $headers = $returns->map(fn (StockReturn $r) => [
            'reference' => $r->reference, 'date' => $r->return_date->toDateString(), 'customer' => $r->customer_name,
            'order' => $r->sale?->sales_id, 'delivery_note' => $r->delivery_note, 'reason' => $r->reasonLabel(),
            'status' => $r->statusLabel(), 'units' => (float) $r->units, 'value' => (float) $r->total_value,
            'decided_by' => $r->decider?->name, 'month' => $r->return_date->format('Y-m'),
        ])->all();

        $lines = [];
        $conditions = [];
        foreach (StockReturnItem::query()->with(['stockReturn', 'product:id,type,name', 'production:id,batch_id'])
            ->whereHas('stockReturn', fn ($q) => $q->where('status', StockReturn::APPROVED)->whereBetween('return_date', DateBounds::between($from, $to)))
            ->get() as $item) {
            $return = $item->stockReturn;
            $base = [
                'reference' => $return->reference, 'date' => $return->return_date->toDateString(), 'customer' => $return->customer_name,
                'product' => (string) ($item->product?->type ?: $item->product?->name), 'batch' => (string) $item->production?->batch_id,
                'reason' => $return->reasonLabel(), 'month' => $return->return_date->format('Y-m'),
            ];
            $lines[] = $base + [
                'sold' => (float) $item->quantity_sold_reported, 'returned' => (float) $item->quantity_returned,
                'restocked' => (float) $item->quantity_restocked, 'unsellable' => $item->unsellableQuantity(),
                'damaged' => (float) $item->quantity_damaged, 'expired' => (float) $item->quantity_expired, 'rejected' => (float) $item->quantity_rejected,
                'unit_price' => (float) $item->unit_price, 'value' => (float) $item->line_value,
            ];
            foreach (['quantity_restocked' => 'Sellable – back in stock', 'quantity_damaged' => 'Damaged', 'quantity_expired' => 'Expired', 'quantity_rejected' => 'Rejected'] as $field => $label) {
                if ((float) $item->{$field} > 0) {
                    $conditions[] = $base + ['condition' => $label, 'quantity' => (float) $item->{$field}, 'value' => round((float) $item->{$field} * (float) $item->unit_price, 2)];
                }
            }
        }

        return [
            'sheets' => [
                'lines' => ['name' => 'Returned Lines', 'rows' => $lines, 'columns' => [
                    'reference' => ['Return', 'text', 16], 'date' => ['Return Date', 'date', 13], 'customer' => ['Customer', 'text', 28],
                    'product' => ['Product', 'text', 26], 'batch' => ['Batch', 'text', 14], 'reason' => ['Reason', 'text', 26],
                    'sold' => ['Sold (reported)', 'qty', 14], 'returned' => ['Returned', 'qty', 11], 'restocked' => ['Back in Stock', 'qty', 13],
                    'damaged' => ['Damaged', 'qty', 10], 'expired' => ['Expired', 'qty', 10], 'rejected' => ['Rejected', 'qty', 10],
                    'unsellable' => ['Unsellable', 'qty', 11], 'unit_price' => ['Unit Price (RWF)', 'money', 15], 'value' => ['Credited (RWF)', 'money', 15],
                    'month' => ['Month', 'text', 10],
                ]],
                'conditions' => ['name' => 'Return Conditions', 'rows' => $conditions, 'columns' => [
                    'reference' => ['Return', 'text', 16], 'date' => ['Return Date', 'date', 13], 'customer' => ['Customer', 'text', 28],
                    'product' => ['Product', 'text', 26], 'batch' => ['Batch', 'text', 14], 'condition' => ['Condition', 'text', 24],
                    'quantity' => ['Quantity', 'qty', 11], 'value' => ['Value (RWF)', 'money', 14],
                ]],
                'returns' => ['name' => 'All Returns', 'rows' => $headers, 'columns' => [
                    'reference' => ['Return', 'text', 16], 'date' => ['Return Date', 'date', 13], 'customer' => ['Customer', 'text', 28],
                    'order' => ['Order', 'text', 18], 'delivery_note' => ['Delivery Note', 'text', 16], 'reason' => ['Reason', 'text', 26],
                    'status' => ['Status', 'text', 18], 'units' => ['Units', 'qty', 10], 'value' => ['Value (RWF)', 'money', 14],
                    'decided_by' => ['Decided By', 'text', 20], 'month' => ['Month', 'text', 10],
                ]],
            ],
            'headline' => [
                ['key' => 'approved', 'label' => 'Returns approved', 'kind' => 'count', 'sheet' => 'returns', 'where' => ['status' => 'Approved'], 'format' => 'int'],
                ['key' => 'value', 'label' => 'Value credited to customers', 'note' => 'reduces revenue and what customers owe', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'value', 'format' => 'money', 'emphasis' => true],
                ['key' => 'units', 'label' => 'Units returned', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'returned', 'format' => 'qty'],
                ['key' => 'restocked', 'label' => 'Back in sellable stock', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'restocked', 'format' => 'qty'],
                ['key' => 'unsellable', 'label' => 'Damaged, expired or rejected', 'note' => 'recorded and written off, never resold', 'kind' => 'sum', 'sheet' => 'lines', 'column' => 'unsellable', 'format' => 'qty'],
                ['key' => 'unsellable_rate', 'label' => 'Share unsellable', 'kind' => 'ratio', 'num' => 'unsellable', 'den' => 'units', 'format' => 'pct'],
                ['key' => 'pending', 'label' => 'Waiting for inspection', 'note' => 'not yet in stock or credited', 'kind' => 'count', 'sheet' => 'returns', 'where' => ['status' => 'Pending inspection'], 'format' => 'int'],
            ],
            'tables' => [
                ['title' => 'By product', 'sheet' => 'lines', 'group' => 'product', 'group_label' => 'Product', 'sort' => 'returned', 'measures' => [
                    ['key' => 'returned', 'label' => 'Returned', 'kind' => 'sum', 'column' => 'returned', 'format' => 'qty'],
                    ['key' => 'restocked', 'label' => 'Back in Stock', 'kind' => 'sum', 'column' => 'restocked', 'format' => 'qty'],
                    ['key' => 'unsellable', 'label' => 'Unsellable', 'kind' => 'sum', 'column' => 'unsellable', 'format' => 'qty'],
                    ['key' => 'value', 'label' => 'Credited', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Value', 'kind' => 'share', 'of' => 'value', 'format' => 'pct'],
                ]],
                ['title' => 'By customer', 'sheet' => 'lines', 'group' => 'customer', 'group_label' => 'Customer', 'sort' => 'value', 'measures' => [
                    ['key' => 'returned', 'label' => 'Returned', 'kind' => 'sum', 'column' => 'returned', 'format' => 'qty'],
                    ['key' => 'unsellable', 'label' => 'Unsellable', 'kind' => 'sum', 'column' => 'unsellable', 'format' => 'qty'],
                    ['key' => 'value', 'label' => 'Credited', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Value', 'kind' => 'share', 'of' => 'value', 'format' => 'pct'],
                ]],
                ['title' => 'By reason', 'sheet' => 'lines', 'group' => 'reason', 'group_label' => 'Reason', 'sort' => 'returned', 'measures' => [
                    ['key' => 'returned', 'label' => 'Returned', 'kind' => 'sum', 'column' => 'returned', 'format' => 'qty'],
                    ['key' => 'value', 'label' => 'Credited', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Units', 'kind' => 'share', 'of' => 'returned', 'format' => 'pct'],
                ]],
                ['title' => 'Sellable versus damaged, expired or rejected', 'sheet' => 'conditions', 'group' => 'condition', 'group_label' => 'Condition', 'sort' => 'quantity', 'measures' => [
                    ['key' => 'quantity', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'value', 'label' => 'Value', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Units', 'kind' => 'share', 'of' => 'quantity', 'format' => 'pct'],
                ]],
                ['title' => 'Returns by month', 'sheet' => 'lines', 'group' => 'month', 'group_label' => 'Month', 'sort' => 'label', 'measures' => [
                    ['key' => 'returned', 'label' => 'Returned', 'kind' => 'sum', 'column' => 'returned', 'format' => 'qty'],
                    ['key' => 'value', 'label' => 'Credited', 'kind' => 'sum', 'column' => 'value', 'format' => 'money'],
                ]],
            ],
            'notes' => [
                'Covers returns dated in the period. Figures on the Summary count approved returns only: nothing moves into stock or onto the customer\'s account until a return is inspected and approved. All Returns lists every status.',
                'Credited is the value of the goods returned at their original selling price. It is taken off the original order, reducing revenue on the return date and what the customer owes, without creating a second sale or payment.',
                'Units found damaged, expired or rejected at inspection are recorded and written off; only units passed as sellable go back on their batch.',
            ],
        ];
    }

    private function samples(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $all = ProductionSample::query()->with(['product:id,type,name', 'production:id,batch_id', 'approver:id,name', 'creator:id,name'])
            ->whereBetween('sample_date', DateBounds::between($from, $to))
            ->orderBy('sample_date')->orderBy('id')->get();

        $row = fn (ProductionSample $s) => [
            'reference' => $s->reference, 'date' => $s->sample_date->toDateString(), 'product' => (string) ($s->product?->type ?: $s->product?->name),
            'batch' => (string) $s->production?->batch_id, 'recipient_type' => $s->recipientTypeLabel(), 'recipient' => $s->recipient_name,
            'purpose' => $s->purpose, 'requested_by' => $s->requested_by_name ?: $s->creator?->name, 'approved_by' => $s->approver?->name,
            'received_by' => $s->received_by_name, 'status' => $s->statusLabel(), 'quantity' => (float) $s->quantity,
            'unit_cost' => $s->unit_cost !== null ? round((float) $s->unit_cost, 2) : null, 'cost' => $s->cost_value !== null ? (float) $s->cost_value : null,
            'month' => $s->sample_date->format('Y-m'),
        ];
        $columns = [
            'reference' => ['Sample', 'text', 16], 'date' => ['Date', 'date', 13], 'product' => ['Product', 'text', 26], 'batch' => ['Batch', 'text', 14],
            'recipient_type' => ['Recipient Type', 'text', 20], 'recipient' => ['Recipient', 'text', 28], 'purpose' => ['Purpose', 'text', 28],
            'requested_by' => ['Requested By', 'text', 20], 'approved_by' => ['Approved By', 'text', 20], 'received_by' => ['Received By', 'text', 20],
            'status' => ['Status', 'text', 16], 'quantity' => ['Quantity', 'qty', 11], 'unit_cost' => ['Unit Cost (RWF)', 'money', 15],
            'cost' => ['Cost (RWF)', 'money', 14], 'month' => ['Month', 'text', 10],
        ];

        return [
            'sheets' => [
                'given' => ['name' => 'Samples Given', 'rows' => $all->where('status', ProductionSample::APPROVED)->map($row)->values()->all(), 'columns' => $columns],
                'requests' => ['name' => 'All Requests', 'rows' => $all->map($row)->all(), 'columns' => $columns],
            ],
            'headline' => [
                ['key' => 'count', 'label' => 'Samples given', 'kind' => 'count', 'sheet' => 'given', 'format' => 'int'],
                ['key' => 'units', 'label' => 'Units given away', 'kind' => 'sum', 'sheet' => 'given', 'column' => 'quantity', 'format' => 'qty'],
                ['key' => 'cost', 'label' => 'Internal cost of samples', 'note' => 'at batch raw-material cost; not revenue', 'kind' => 'sum', 'sheet' => 'given', 'column' => 'cost', 'format' => 'money', 'emphasis' => true],
                ['key' => 'pending', 'label' => 'Requests waiting for approval', 'kind' => 'count', 'sheet' => 'requests', 'where' => ['status' => 'Pending approval'], 'format' => 'int'],
            ],
            'tables' => [
                ['title' => 'By product', 'sheet' => 'given', 'group' => 'product', 'group_label' => 'Product', 'sort' => 'units', 'measures' => [
                    ['key' => 'units', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                    ['key' => 'share', 'label' => '% of Cost', 'kind' => 'share', 'of' => 'cost', 'format' => 'pct'],
                ]],
                ['title' => 'By batch', 'sheet' => 'given', 'group' => 'batch', 'group_label' => 'Batch', 'sort' => 'units', 'measures' => [
                    ['key' => 'product', 'label' => 'Product', 'kind' => 'first', 'column' => 'product', 'format' => 'text'],
                    ['key' => 'units', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
                ['title' => 'By recipient', 'sheet' => 'given', 'group' => 'recipient', 'group_label' => 'Recipient', 'sort' => 'units', 'measures' => [
                    ['key' => 'type', 'label' => 'Type', 'kind' => 'first', 'column' => 'recipient_type', 'format' => 'text'],
                    ['key' => 'count', 'label' => 'Samples', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'units', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
                ['title' => 'By purpose', 'sheet' => 'given', 'group' => 'purpose', 'group_label' => 'Purpose', 'sort' => 'units', 'measures' => [
                    ['key' => 'units', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
                ['title' => 'Samples by month', 'sheet' => 'given', 'group' => 'month', 'group_label' => 'Month', 'sort' => 'label', 'measures' => [
                    ['key' => 'units', 'label' => 'Units', 'kind' => 'sum', 'column' => 'quantity', 'format' => 'qty'],
                    ['key' => 'cost', 'label' => 'Cost', 'kind' => 'sum', 'column' => 'cost', 'format' => 'money'],
                ]],
            ],
            'notes' => [
                'Samples are product taken from a batch and given away free. They are never sales: no invoice is raised and no revenue recorded.',
                'Cost is the batch\'s raw-material cost per unit, frozen when the sample is approved. The income statement shows it as a promotion expense, not as cost of goods sold.',
                'Summary figures count approved samples; All Requests lists every request with its status.',
            ],
        ];
    }

    private function customerBalances(CarbonImmutable $to): array
    {
        $credits = StockReturn::query()->where('status', StockReturn::APPROVED)->where('return_date', '<=', DateBounds::endOfDay($to))
            ->groupBy('sale_id')->selectRaw('sale_id, SUM(total_value) as credited')->pluck('credited', 'sale_id');
        $paid = DB::table('sale_payments')->where('paid_at', '<=', DateBounds::endOfDay($to))
            ->groupBy('sale_id')->selectRaw('sale_id, SUM(amount) as paid')->pluck('paid', 'sale_id');

        $rows = Sale::query()->with('customer:id,name')
            ->where('sale_date', '<=', DateBounds::endOfDay($to))
            ->orderBy('sale_date')->orderBy('id')
            ->get(['id', 'sales_id', 'customer_id', 'customer_name', 'sale_date', 'delivery_status', 'payment_status', 'total_revenue', 'due_date'])
            ->map(function (Sale $sale) use ($credits, $paid) {
                $invoiced = (float) $sale->total_revenue;
                $credited = (float) ($credits[$sale->id] ?? 0);
                $received = (float) ($paid[$sale->id] ?? 0);

                return [
                    'customer' => trim((string) ($sale->customer?->name ?: $sale->customer_name)),
                    'order' => $sale->sales_id,
                    'date' => CarbonImmutable::parse($sale->sale_date)->toDateString(),
                    'delivery' => (string) $sale->delivery_status,
                    'invoiced' => $invoiced,
                    'credited' => $credited,
                    'net' => round($invoiced - $credited, 2),
                    'paid' => $received,
                    'balance' => round($invoiced - $credited - $received, 2),
                    'due_date' => $sale->due_date?->toDateString(),
                    'status' => (string) $sale->payment_status,
                ];
            })->all();

        return [
            'sheets' => ['orders' => ['name' => 'Orders', 'rows' => $rows, 'columns' => [
                'customer' => ['Customer', 'text', 30], 'order' => ['Order', 'text', 18], 'date' => ['Order Date', 'date', 13],
                'delivery' => ['Delivery', 'text', 12], 'invoiced' => ['Invoiced (RWF)', 'money', 15], 'credited' => ['Returns Credited (RWF)', 'money', 21],
                'net' => ['Net Charged (RWF)', 'money', 17], 'paid' => ['Paid (RWF)', 'money', 14], 'balance' => ['Balance (RWF)', 'money', 15],
                'due_date' => ['Due Date', 'date', 13], 'status' => ['Payment Status', 'text', 15],
            ]]],
            'headline' => [
                ['key' => 'invoiced', 'label' => 'Invoiced to customers', 'kind' => 'sum', 'sheet' => 'orders', 'column' => 'invoiced', 'format' => 'money'],
                ['key' => 'credited', 'label' => 'Credited for returned goods', 'kind' => 'sum', 'sheet' => 'orders', 'column' => 'credited', 'format' => 'money'],
                ['key' => 'paid', 'label' => 'Payments received', 'kind' => 'sum', 'sheet' => 'orders', 'column' => 'paid', 'format' => 'money'],
                ['key' => 'balance', 'label' => 'Owed by customers (net)', 'note' => 'negative means credit owed back', 'kind' => 'sum', 'sheet' => 'orders', 'column' => 'balance', 'format' => 'money', 'emphasis' => true],
            ],
            'tables' => [
                ['title' => 'Balance by customer', 'sheet' => 'orders', 'group' => 'customer', 'group_label' => 'Customer', 'sort' => 'balance', 'measures' => [
                    ['key' => 'orders', 'label' => 'Orders', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'invoiced', 'label' => 'Invoiced', 'kind' => 'sum', 'column' => 'invoiced', 'format' => 'money'],
                    ['key' => 'credited', 'label' => 'Returns Credited', 'kind' => 'sum', 'column' => 'credited', 'format' => 'money'],
                    ['key' => 'paid', 'label' => 'Paid', 'kind' => 'sum', 'column' => 'paid', 'format' => 'money'],
                    ['key' => 'balance', 'label' => 'Balance', 'kind' => 'sum', 'column' => 'balance', 'format' => 'money'],
                ]],
            ],
            'notes' => [
                'Balances as at the end of the period, over every order dated up to then, whatever its status. Balance = invoiced - returns credited - payments received.',
                'Returns are credited against the order they came back from; payments are those confirmed in the payments ledger.',
            ],
        ];
    }

    private function stockMovements(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $labels = [
            'produced' => 'Produced', 'sold' => 'Sold', 'sale_reversal' => 'Sale edited (restored)', 'adjustment' => 'Adjustment',
            'damaged' => 'Damaged', 'return_restock' => 'Return – back in stock', 'return_received' => 'Return – unsellable received',
            'return_written_off' => 'Return – written off', 'sample' => 'Sample given', 'sample_reversal' => 'Sample reversed',
        ];
        $rows = StockMovement::query()->with(['product:id,type,name', 'production:id,batch_id', 'user:id,name'])
            ->whereBetween('moved_at', DateBounds::between($from, $to))
            ->orderBy('moved_at')->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [
                'date' => $m->moved_at?->toDateString(), 'type' => $labels[$m->type] ?? ucfirst(str_replace('_', ' ', (string) $m->type)),
                'product' => (string) ($m->product?->type ?: $m->product?->name), 'batch' => (string) $m->production?->batch_id,
                'in' => (float) $m->quantity > 0 ? (float) $m->quantity : 0.0, 'out' => (float) $m->quantity < 0 ? -1 * (float) $m->quantity : 0.0,
                'net' => (float) $m->quantity, 'reference' => trim(str_replace('_', ' ', (string) $m->reference_type).' #'.$m->reference_id, ' #'),
                'notes' => (string) $m->notes, 'user' => $m->user?->name, 'month' => $m->moved_at?->format('Y-m'),
            ])->all();

        return [
            'sheets' => ['movements' => ['name' => 'Movements', 'rows' => $rows, 'columns' => [
                'date' => ['Date', 'date', 13], 'type' => ['Movement', 'text', 26], 'product' => ['Product', 'text', 26], 'batch' => ['Batch', 'text', 14],
                'in' => ['In', 'qty', 10], 'out' => ['Out', 'qty', 10], 'net' => ['Net', 'qty', 10], 'reference' => ['Reference', 'text', 22],
                'notes' => ['Notes', 'text', 48], 'user' => ['By', 'text', 18], 'month' => ['Month', 'text', 10],
            ]]],
            'headline' => [
                ['key' => 'count', 'label' => 'Movements recorded', 'kind' => 'count', 'sheet' => 'movements', 'format' => 'int'],
                ['key' => 'in', 'label' => 'Units into finished goods', 'kind' => 'sum', 'sheet' => 'movements', 'column' => 'in', 'format' => 'qty'],
                ['key' => 'out', 'label' => 'Units out of finished goods', 'kind' => 'sum', 'sheet' => 'movements', 'column' => 'out', 'format' => 'qty'],
                ['key' => 'net', 'label' => 'Net change', 'kind' => 'sum', 'sheet' => 'movements', 'column' => 'net', 'format' => 'qty', 'emphasis' => true],
            ],
            'tables' => [
                ['title' => 'By movement type', 'sheet' => 'movements', 'group' => 'type', 'group_label' => 'Movement', 'sort' => 'count', 'measures' => [
                    ['key' => 'count', 'label' => 'Entries', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'in', 'label' => 'In', 'kind' => 'sum', 'column' => 'in', 'format' => 'qty'],
                    ['key' => 'out', 'label' => 'Out', 'kind' => 'sum', 'column' => 'out', 'format' => 'qty'],
                ]],
                ['title' => 'By product', 'sheet' => 'movements', 'group' => 'product', 'group_label' => 'Product', 'sort' => 'count', 'measures' => [
                    ['key' => 'count', 'label' => 'Entries', 'kind' => 'count', 'format' => 'int'],
                    ['key' => 'in', 'label' => 'In', 'kind' => 'sum', 'column' => 'in', 'format' => 'qty'],
                    ['key' => 'out', 'label' => 'Out', 'kind' => 'sum', 'column' => 'out', 'format' => 'qty'],
                    ['key' => 'net', 'label' => 'Net', 'kind' => 'sum', 'column' => 'net', 'format' => 'qty'],
                ]],
            ],
            'notes' => [
                'Every recorded movement of finished goods in the period: production, sales and edits, damage, returns (back in stock, or received and written off) and samples.',
                'Unsellable returns appear twice, in then straight out, so the history shows them without them ever adding to sellable stock.',
            ],
        ];
    }

    /** Raw materials drawn into batches dated in the window, one row per material line. */
    private function usageRows(CarbonImmutable $from, CarbonImmutable $to, string $name = 'Production Usage'): array
    {
        $rows = array_map(fn (array $line) => [
            'batch' => $line['batch_id'],
            'date' => $line['production_date'],
            'product' => $line['product'],
            'record' => $line['inventory_id'],
            'item' => $line['item_name'],
            'supplier' => $line['supplier'],
            'qty' => $line['quantity'],
            'unit_cost' => $line['unit_cost'],
            'cost' => $line['cost'],
            'month' => substr($line['production_date'], 0, 7),
            'flag' => $line['cost'] === null ? 'Lot has no purchase price' : '',
        ], $this->costing->consumption($from->toDateString(), $to->toDateString()));

        return ['name' => $name, 'rows' => $rows, 'columns' => [
            'batch' => ['Batch', 'text', 16], 'date' => ['Batch Date', 'date', 13], 'product' => ['Product', 'text', 26],
            'record' => ['Purchase Record ID', 'int', 18], 'item' => ['Item', 'text', 28], 'supplier' => ['Supplier', 'text', 28],
            'qty' => ['Qty Used', 'qty', 11], 'unit_cost' => ['Unit Cost (RWF)', 'money', 15], 'cost' => ['Cost of Goods (RWF)', 'money', 19],
            'month' => ['Month', 'text', 10], 'flag' => ['Review Flag', 'text', 28],
        ]];
    }

    // ── Summary engine ──────────────────────────────────────────────────────

    /**
     * Compute every headline and breakdown figure from the detail rows.
     *
     * Headline kinds: sum (a column), count (rows), ratio (two earlier headlines).
     * Table measure kinds: sum, count, first (value from the group's first row),
     * share (of the table total of another measure) and ratio (two measures in the row).
     * A `where` filter of ['column' => '!'] keeps rows where that column is not blank.
     */
    public function summarise(array $report): array
    {
        $headline = [];
        foreach ($report['headline'] as $metric) {
            $rows = isset($metric['sheet']) ? $this->filter($report['sheets'][$metric['sheet']]['rows'], $metric['where'] ?? []) : [];
            $metric['value'] = match ($metric['kind']) {
                'sum' => $this->sum($rows, $metric['column']),
                'count' => count($rows),
                'ratio' => $this->ratio($headline[$metric['num']]['value'] ?? null, $headline[$metric['den']]['value'] ?? null),
            };
            $headline[$metric['key']] = $metric;
        }

        $report['headline'] = array_values($headline);
        $report['tables'] = array_map(fn (array $table) => $this->table($table, $report['sheets'][$table['sheet']]['rows']), $report['tables']);

        return $report;
    }

    private function table(array $table, array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row[$table['group']] ?? ''));
            $groups[$label === '' ? '(blank)' : $label][] = $row;
        }

        $lines = [];
        foreach ($groups as $label => $groupRows) {
            $values = [];
            foreach ($table['measures'] as $m) {
                $values[$m['key']] = match ($m['kind']) {
                    'sum' => $this->sum($groupRows, $m['column']),
                    'count' => count($groupRows),
                    'first' => $groupRows[0][$m['column']] ?? null,
                    default => null,
                };
            }
            $lines[] = ['label' => (string) $label, 'values' => $values];
        }

        $total = [];
        foreach ($table['measures'] as $m) {
            $total[$m['key']] = in_array($m['kind'], ['sum', 'count'], true)
                ? round(array_sum(array_map(fn ($l) => $l['values'][$m['key']], $lines)), 2)
                : null;
        }

        $derive = function (array $values) use ($table, $total): array {
            foreach ($table['measures'] as $m) {
                if ($m['kind'] === 'share') {
                    $values[$m['key']] = $this->ratio($values[$m['of']], $total[$m['of']]) ?? 0.0;
                } elseif ($m['kind'] === 'ratio') {
                    $values[$m['key']] = $this->ratio($values[$m['num']], $values[$m['den']]);
                }
            }

            return $values;
        };
        $lines = array_map(fn ($l) => ['label' => $l['label'], 'values' => $derive($l['values'])], $lines);
        $total = $derive($total);

        $sort = $table['sort'];
        usort($lines, function (array $a, array $b) use ($sort): int {
            $order = match ($sort) {
                'label' => 0,
                'date' => strcmp((string) $a['values']['date'], (string) $b['values']['date']),
                default => $b['values'][$sort] <=> $a['values'][$sort],
            };

            return $order ?: strnatcasecmp($a['label'], $b['label']);
        });

        return $table + ['lines' => $lines, 'total' => $total];
    }

    private function filter(array $rows, array $where): array
    {
        return array_values(array_filter($rows, function (array $row) use ($where): bool {
            foreach ($where as $column => $expected) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($expected === '!' ? $value === '' : $value !== $expected) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function sum(array $rows, string $column): float
    {
        return round(array_sum(array_map(fn ($row) => (float) ($row[$column] ?? 0), $rows)), 2);
    }

    private function ratio(int|float|null $num, int|float|null $den): ?float
    {
        return $num === null || ! $den ? null : $num / $den;
    }
}
