<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\DateBounds;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamPerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = (string) $request->query('period', 'this_month');
        [$start, $end] = $this->resolveDates($period, $request->query('start_date'), $request->query('end_date'));

        $users = User::query()->orderBy('name')->get();

        // --- Sales performance, attributed to the user who recorded each sale ---
        $salesAgg = $this->aggregate(
            Sale::query()->whereNotNull('created_by')
                ->when($start && $end, fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)))
                ->selectRaw('created_by, COUNT(*) as orders, COALESCE(SUM(total_revenue), 0) as revenue')
                ->groupBy('created_by')
                ->get()
                ->keyBy('created_by')
        );

        $cashAgg = SalePayment::query()
            ->when($start && $end, fn ($q) => $q->whereBetween('paid_at', DateBounds::between($start, $end)))
            ->whereNotNull('recorded_by')
            ->selectRaw('recorded_by, COALESCE(SUM(amount), 0) as cash')
            ->groupBy('recorded_by')
            ->get()
            ->keyBy('recorded_by');

        $salesRows = $users
            ->filter(fn (User $u) => $u->hasRole(User::ROLE_SALES, User::ROLE_MANAGER, User::ROLE_OWNER))
            ->map(function (User $u) use ($salesAgg, $cashAgg) {
                $orders = (int) ($salesAgg[$u->id]->orders ?? 0);
                $revenue = (float) ($salesAgg[$u->id]->revenue ?? 0);
                $cash = (float) ($cashAgg[$u->id]->cash ?? 0);
                $target = (float) ($u->sales_target ?? 0);
                $rate = (float) ($u->commission_rate ?? 0);

                return [
                    'user' => $u,
                    'orders' => $orders,
                    'revenue' => $revenue,
                    'avg_sale' => $orders > 0 ? $revenue / $orders : 0.0,
                    'cash_collected' => $cash,
                    'target' => $target,
                    'attainment' => $target > 0 ? round($revenue / $target * 100, 1) : null,
                    'commission' => $rate > 0 ? round($revenue * $rate / 100, 2) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['orders'] > 0 || $row['target'] > 0 || $row['user']->isSales())
            ->sortByDesc('revenue')
            ->values();

        // --- Production output, attributed to the worker (employee) assigned to each batch.
        //     This measures people who make product, whether or not they log in. ---
        $prodAgg = Production::query()->whereNotNull('employee_id')
            ->when($start && $end, fn ($q) => $q->whereBetween('production_date', DateBounds::between($start, $end)))
            ->selectRaw('employee_id, COUNT(*) as batches, COALESCE(SUM(quantity_produced), 0) as produced, COALESCE(SUM(damaged), 0) as damaged')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $workers = Employee::query()->whereIn('id', $prodAgg->keys())->get()->keyBy('id');

        $productionRows = $prodAgg
            ->map(function ($agg) use ($workers) {
                $produced = (float) $agg->produced;
                $damaged = (float) $agg->damaged;
                $total = $produced + $damaged;
                $worker = $workers[$agg->employee_id] ?? null;

                return [
                    'name' => $worker?->name ?? 'Unknown worker',
                    'position' => $worker?->position,
                    'batches' => (int) $agg->batches,
                    'produced' => $produced,
                    'damaged' => $damaged,
                    'damage_rate' => $total > 0 ? round($damaged / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('produced')
            ->values();

        $totals = [
            'revenue' => $salesRows->sum('revenue'),
            'orders' => $salesRows->sum('orders'),
            'cash_collected' => $salesRows->sum('cash_collected'),
            'commission' => $salesRows->sum('commission'),
            'produced' => $productionRows->sum('produced'),
        ];

        return view('admin.team-performance.index', [
            'period' => $period,
            'start_date' => $start?->format('Y-m-d'),
            'end_date' => $end?->format('Y-m-d'),
            'salesRows' => $salesRows,
            'productionRows' => $productionRows,
            'totals' => $totals,
            'unattributedSales' => Sale::query()
                ->when($start && $end, fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)))
                ->whereNull('created_by')
                ->count(),
            'unattributedProduction' => Production::query()
                ->when($start && $end, fn ($q) => $q->whereBetween('production_date', DateBounds::between($start, $end)))
                ->whereNull('employee_id')
                ->count(),
        ]);
    }

    /**
     * Eloquent collections keyed by a numeric column come back keyed by string;
     * normalize so $agg[$user->id] works.
     *
     * @param  \Illuminate\Support\Collection  $collection
     * @return \Illuminate\Support\Collection
     */
    private function aggregate($collection)
    {
        return $collection->keyBy(fn ($row) => (int) $row->created_by);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveDates(string $period, ?string $startDate, ?string $endDate): array
    {
        if ($period !== 'custom' && $period !== 'all_time') {
            return match ($period) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
                'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [now()->startOfMonth(), now()->endOfMonth()], // this_month
            };
        }

        if ($period === 'custom' && $startDate && $endDate) {
            return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
        }

        return [null, null];
    }
}
