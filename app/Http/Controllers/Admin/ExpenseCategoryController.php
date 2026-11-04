<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExpenseCategoryRequest;
use App\Http\Requests\Admin\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.expenses.categories.index', [
            'cogsCategories' => ExpenseCategory::query()
                ->ofType(ExpenseCategory::TYPE_COGS)
                ->withCount('expenses')
                ->orderBy('name')
                ->get(),
            'operatingCategories' => ExpenseCategory::query()
                ->ofType(ExpenseCategory::TYPE_OPERATING)
                ->withCount('expenses')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->validated());

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('status', 'Category created successfully.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('status', 'Category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $count = $expenseCategory->expenses()->count();

        if ($count > 0) {
            return redirect()
                ->route('admin.expense-categories.index')
                ->with('warning', "Cannot delete category — {$count} expense record(s) are linked to it. Reassign or delete those expenses first.");
        }

        $expenseCategory->delete();

        return redirect()
            ->route('admin.expense-categories.index')
            ->with('status', 'Category deleted successfully.');
    }
}
