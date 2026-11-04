<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }
                $price = (float) request()->input('price', 0);
                if ((float) $value <= $price) {
                    $fail('Compare at price must be higher than the selling price.');
                }
            }],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'sku' => ['nullable', 'string', 'max:100'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'requires_qc_release' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'product_image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /** An unticked checkbox sends nothing; record that as "no release needed". */
    protected function prepareForValidation(): void
    {
        $this->merge(['requires_qc_release' => $this->boolean('requires_qc_release')]);
    }
}
