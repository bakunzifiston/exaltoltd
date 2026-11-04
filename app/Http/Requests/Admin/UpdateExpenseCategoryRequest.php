<?php

namespace App\Http\Requests\Admin;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ExpenseCategory $category */
        $category = $this->route('expense_category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories')
                    ->where(fn ($query) => $query->where('type', $this->input('type')))
                    ->ignore($category->id),
            ],
            'type' => ['required', 'string', Rule::in([ExpenseCategory::TYPE_COGS, ExpenseCategory::TYPE_OPERATING])],
        ];
    }
}
