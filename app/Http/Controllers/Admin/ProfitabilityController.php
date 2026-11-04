<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductProfitabilityService;
use App\Services\RatioService;
use App\Services\SaleCostingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profitability by product and batch, plus the lender ratio pack.
 *
 * Both read the FIFO cost frozen onto each sale line. Costing is a backfill because FIFO
 * depends on the order of every purchase and sale, so a backdated entry changes the cost
 * of everything after it.
 */
class ProfitabilityController extends Controller
{
    public function index(
        Request $request,
        ProductProfitabilityService $profitability,
        RatioService $ratios,
    ): View {
        [$from, $to] = $this->window($request);

        return view('admin.reports.profitability', [
            'from' => $from,
            'to' => $to,
            'summary' => $profitability->summary($from, $to),
            'products' => $profitability->byProduct($from, $to),
            'batches' => $profitability->byBatch($from, $to),
            'costWarnings' => $profitability->costQualityWarnings($from, $to),
            'ratios' => $ratios->forPeriod(
                CarbonImmutable::parse($from)->startOfMonth()->toDateString(),
                $to
            ),
        ]);
    }

    public function recost(Request $request, SaleCostingService $costing): RedirectResponse
    {
        [$from, $to] = $this->window($request);
        $result = $costing->recost($from, $to);

        $costed = $result['costed_from_batch'] + $result['costed_from_purchases'];
        $message = $costed.' of '.$result['lines'].' sale lines costed ('
            .$result['costed_from_batch'].' from production batches, '
            .$result['costed_from_purchases'].' from purchase layers).';

        if ($result['uncosted'] > 0) {
            $message .= ' '.$result['uncosted'].' could not be costed and are shown without a margin, rather than being valued at zero. '
                .'The usual cause is a sale line with no production batch, or a batch whose raw-material lots carry no cost.';
        }

        return redirect()
            ->route('admin.reports.profitability', ['from' => $from, 'to' => $to])
            ->with('status', $message);
    }

    /** @return array{0: string, 1: string} */
    private function window(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return [
            $data['from'] ?? CarbonImmutable::now()->startOfYear()->toDateString(),
            $data['to'] ?? CarbonImmutable::now()->endOfYear()->toDateString(),
        ];
    }
}
