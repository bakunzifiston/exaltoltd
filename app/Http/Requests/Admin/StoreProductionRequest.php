<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ChecksRawMaterialStock;
use App\Http\Requests\Admin\Concerns\ValidatesTraceabilityFields;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductionRequest extends FormRequest
{
    use ChecksRawMaterialStock;
    use ValidatesTraceabilityFields;

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn () => $this->validateRawMaterialStock($validator));
    }

    public function rules(): array
    {
        return [
            // A batch number may repeat across products (one batch, several packagings), but
            // not within the same product — that is double-entry, not extra packaging.
            'batch_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('productions', 'batch_id')->where('product_id', $this->input('product_id')),
            ],
            'product_id' => ['required', 'exists:products,id'],
            'barcode' => ['required', 'string', 'max:255'],
            'quantity_produced' => ['required', 'numeric', 'min:0'],
            'damaged' => ['required', 'numeric', 'min:0'],
            'production_date' => ['required', 'date'],
            'responsible_staff' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'quality_control_notes' => ['nullable', 'string'],
            'inventory_record_id' => ['required', 'array', 'min:1'],
            'inventory_record_id.*.inventory_id' => ['required', 'exists:inventory_records,id'],
            'inventory_record_id.*.quantity_used' => ['required', 'numeric', 'gt:0'],
        ] + $this->traceabilityRules();
    }

    public function messages(): array
    {
        return [
            'batch_id.unique' => 'This batch number has already been recorded for the selected product.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $product = Product::query()->find($this->input('product_id'));

        if ($product?->barcode) {
            $this->merge(['barcode' => $product->barcode]);
        }

        // Trim so " SHK/BN/108" cannot slip past the uniqueness check as a separate batch.
        if ($this->has('batch_id')) {
            $this->merge(['batch_id' => trim((string) $this->input('batch_id'))]);
        }
    }
}
