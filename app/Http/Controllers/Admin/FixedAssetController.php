<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\FixedAsset;
use App\Models\Supplier;
use App\Support\TablePageSize;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The fixed-asset register that drives depreciation.
 *
 * One schedule per asset feeds the income statement, the balance sheet and the cash flow
 * add-back, replacing the workbook line that applied RWF 41,667 a month inconsistently —
 * starting in January on one sheet and February on another.
 */
class FixedAssetController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.finance.fixed-assets', [
            'assets' => FixedAsset::query()->orderByDesc('acquisition_date')->paginate(TablePageSize::resolve($request, 25))->withQueryString(),
            'asOf' => CarbonImmutable::now()->endOfMonth(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FixedAsset::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Asset registered. Depreciation starts in the month of acquisition.');
    }

    public function update(Request $request, FixedAsset $fixedAsset): RedirectResponse
    {
        $fixedAsset->update($this->validated($request));

        return back()->with('status', 'Asset updated. Every statement that uses its schedule will change.');
    }

    public function destroy(FixedAsset $fixedAsset): RedirectResponse
    {
        $fixedAsset->delete();

        return back()->with('status', 'Asset removed from the register.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'acquisition_date' => ['required', 'date_format:Y-m-d'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'salvage_value' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2', 'lte:cost'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'disposal_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:acquisition_date'],
            'disposal_proceeds' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
