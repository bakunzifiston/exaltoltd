<?php

namespace App\Http\Requests\Admin;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories')->where(function ($query): void {
                    $query->where('type', $this->input('type'));
                }),
            ],
            'type' => ['required', 'string', Rule::in([ExpenseCategory::TYPE_COGS, ExpenseCategory::TYPE_OPERATING])],
        ];
    }
}
