<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpeningBalance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The cut-over figures every balance sheet is built on.
 *
 * One dated set replaces importing the historical workbooks, whose two 2025 versions
 * disagree by RWF 8,395,000. A set that does not balance is rejected outright: every
 * column of every statement built on it would be out by the same amount.
 */
class OpeningBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $dates = OpeningBalance::query()
            ->selectRaw('as_of_date')->distinct()->orderByDesc('as_of_date')->pluck('as_of_date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->values();

        $selected = $request->query('as_of_date') ?: $dates->first() ?: now()->startOfYear()->subDay()->toDateString();

        $amounts = OpeningBalance::query()
            ->whereDate('as_of_date', $selected)
            ->pluck('amount', 'line_key')
            ->map(fn ($v) => (float) $v)
            ->all();

        return view('admin.finance.opening-balances', [
            'dates' => $dates,
            'selected' => $selected,
            'amounts' => $amounts,
            'lines' => OpeningBalance::LINES,
            'assetLines' => OpeningBalance::ASSET_LINES,
            'contraLines' => OpeningBalance::CONTRA_ASSET_LINES,
            'liabilityLines' => OpeningBalance::LIABILITY_LINES,
            'equityLines' => OpeningBalance::EQUITY_LINES,
            'check' => $amounts === [] ? null : OpeningBalance::balanceCheck($selected),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'as_of_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'between:-999999999999,999999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $amounts = [];
        foreach (array_keys(OpeningBalance::LINES) as $key) {
            $amounts[$key] = round((float) ($data['amounts'][$key] ?? 0), 2);
        }

        $sum = fn (array $keys) => array_sum(array_map(fn ($k) => $amounts[$k], $keys));
        $assets = $sum(OpeningBalance::ASSET_LINES) - $sum(OpeningBalance::CONTRA_ASSET_LINES);
        $difference = round($assets - $sum(OpeningBalance::LIABILITY_LINES) - $sum(OpeningBalance::EQUITY_LINES), 2);

        // Refusing here is the whole point: an unbalanced cut-over silently corrupts
        // every month of every statement derived from it.
        if (abs($difference) >= 0.01) {
            throw ValidationException::withMessages([
                'amounts' => 'These figures do not balance. Assets less liabilities less equity is RWF '
                    .number_format($difference, 2).', and must be zero. Retained earnings is usually the balancing figure.',
            ]);
        }

        foreach ($amounts as $key => $amount) {
            OpeningBalance::updateOrCreate(
                ['as_of_date' => $data['as_of_date'], 'line_key' => $key],
                ['amount' => $amount, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id],
            );
        }

        return redirect()
            ->route('admin.finance.opening-balances.index', ['as_of_date' => $data['as_of_date']])
            ->with('status', 'Opening balances saved and they balance. Statements starting after '.$data['as_of_date'].' will use them.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['as_of_date' => ['required', 'date_format:Y-m-d']]);
        OpeningBalance::query()->whereDate('as_of_date', $data['as_of_date'])->delete();

        return redirect()->route('admin.finance.opening-balances.index')
            ->with('status', 'Opening balance set for '.$data['as_of_date'].' deleted.');
    }
}
