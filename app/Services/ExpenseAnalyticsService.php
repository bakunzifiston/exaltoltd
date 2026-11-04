<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseAnalyticsService
{
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    /** @var array<int, list<array<string, mixed>>> Priced consumption lines, keyed by year. */
    private array $consumptionByYear = [];

    public function __construct(private readonly ProductionCostingService $production) {}

    /**
     * @return array<int, array{month: int, label: string, cogs_total: float, operating_total: float}>
     */
    public function monthlyTotals(int $year): array
    {
        $rows = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.year', $year)
            ->select(
                'expenses.month',
                'expense_categories.type',
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('expenses.month', 'expense_categories.type')
            ->get();

        // Cost of goods is what production actually used, not what was bought: a purchase
        // only moves cash into stock until a batch draws on it.
        $consumedByMonth = [];
        foreach ($this->consumption($year) as $line) {
            $month = (int) substr($line['production_date'], 5, 2);
            $consumedByMonth[$month] = ($consumedByMonth[$month] ?? 0) + $line['cost'];
        }

        $byMonth = [];
        for ($month = 1; $month <= 12; $month++) {
            $byMonth[$month] = [
                'month' => $month,
                'label' => self::MONTH_LABELS[$month],
                'cogs_total' => round((float) ($consumedByMonth[$month] ?? 0), 2),
                'operating_total' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $month = (int) $row->month;
            $total = (float) $row->total;

            if ($row->type === ExpenseCategory::TYPE_COGS) {
                $byMonth[$month]['cogs_total'] += $total;
            } elseif ($row->type === ExpenseCategory::TYPE_OPERATING) {
                $byMonth[$month]['operating_total'] = $total;
            }
        }

        return array_values($byMonth);
    }

    /**
     * @return array{
     *     total_cogs: float,
     *     total_operating: float,
     *     grand_total: float,
     *     peak_month: int|null,
     *     peak_month_label: string|null,
     *     peak_month_total: float
     * }
     */
    public function yearSummary(int $year): array
    {
        $totals = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.year', $year)
            ->select(
                'expense_categories.type',
                DB::raw('SUM(expenses.amount) as total')
            )
            ->groupBy('expense_categories.type')
            ->pluck('total', 'type');

        $consumedCogs = round(array_sum(array_column($this->consumption($year), 'cost')), 2);

        $totalCogs = (float) ($totals[ExpenseCategory::TYPE_COGS] ?? 0) + $consumedCogs;
        $totalOperating = (float) ($totals[ExpenseCategory::TYPE_OPERATING] ?? 0);

        $monthly = collect($this->monthlyTotals($year));
        $peak = $monthly
            ->map(fn (array $row) => [
                'month' => $row['month'],
                'label' => $row['label'],
                'total' => $row['cogs_total'] + $row['operating_total'],
            ])
            ->sortByDesc('total')
            ->first();

        $peakTotal = (float) ($peak['total'] ?? 0);

        return [
            'total_cogs' => $totalCogs,
            'total_operating' => $totalOperating,
            'grand_total' => $totalCogs + $totalOperating,
            'peak_month' => $peakTotal > 0 ? (int) $peak['month'] : null,
            'peak_month_label' => $peakTotal > 0 ? (string) $peak['label'] : null,
            'peak_month_total' => $peakTotal,
        ];
    }

    /**
     * @return Collection<int, Expense>
     */
    public function topItems(int $year, string $type, int $limit = 5): Collection
    {
        return Expense::query()
            ->with('category')
            ->forYear($year)
            ->ofType($type)
            ->orderByDesc('amount')
            ->limit($limit)
            ->get();
    }

    /**
     * Full-year breakdown by category for a given type.
     *
     * @return array<int, array{name: string, amount: float, percent: float}>
     */
    public function fullYearBreakdown(int $year, string $type): array
    {
        $categories = ExpenseCategory::query()
            ->where('type', $type)
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $amounts = Expense::query()
            ->where('year', $year)
            ->whereIn('expense_category_id', $categories->pluck('id'))
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id');

        $typeTotal = (float) $amounts->sum();

        return $categories->map(function (ExpenseCategory $category) use ($amounts, $typeTotal): array {
            $amount = (float) ($amounts[$category->id] ?? 0);

            return [
                'name' => $category->name,
                'amount' => $amount,
                'percent' => $typeTotal > 0 ? round(($amount / $typeTotal) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Month breakdown by category for a given type.
     * For COGS, raw materials consumed in production are merged in, one line per material.
     *
     * @return array{
     *     items: array<int, array{name: string, amount: float, percent: float, source: string}>,
     *     total: float
     * }
     */
    public function monthBreakdown(int $year, int $month, string $type): array
    {
        $categories = ExpenseCategory::query()
            ->where('type', $type)
            ->orderBy('name')
            ->get();

        $amounts = $categories->isNotEmpty()
            ? Expense::query()
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('expense_category_id', $categories->pluck('id'))
                ->select('expense_category_id', DB::raw('SUM(amount) as total'))
                ->groupBy('expense_category_id')
                ->pluck('total', 'expense_category_id')
            : collect();

        $items = $categories->map(function (ExpenseCategory $category) use ($amounts): array {
            return [
                'name' => $category->name,
                'amount' => (float) ($amounts[$category->id] ?? 0),
                'source' => 'expense',
            ];
        })->values()->all();

        // For COGS, merge the raw materials production consumed this month
        if ($type === ExpenseCategory::TYPE_COGS) {
            $consumed = [];
            foreach ($this->consumption($year) as $line) {
                if ((int) substr($line['production_date'], 5, 2) !== $month) {
                    continue;
                }
                $consumed[$line['item_name']] = ($consumed[$line['item_name']] ?? 0) + $line['cost'];
            }
            ksort($consumed, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($consumed as $name => $amount) {
                $items[] = ['name' => $name, 'amount' => round($amount, 2), 'source' => 'production'];
            }
        }

        $monthTotal = (float) array_sum(array_column($items, 'amount'));

        $items = array_map(function (array $item) use ($monthTotal): array {
            $item['percent'] = $monthTotal > 0 ? round(($item['amount'] / $monthTotal) * 100, 1) : 0.0;

            return $item;
        }, $items);

        // Sort by amount descending
        usort($items, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'items' => $items,
            'total' => $monthTotal,
        ];
    }

    /**
     * Priced raw-material lines drawn into production batches dated in the year.
     *
     * @return list<array<string, mixed>>
     */
    private function consumption(int $year): array
    {
        return $this->consumptionByYear[$year] ??= array_values(array_filter(
            $this->production->consumption($year.'-01-01', $year.'-12-31'),
            fn (array $line) => $line['cost'] !== null,
        ));
    }
}
