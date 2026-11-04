<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ChecksFinishedGoodsStock;
use App\Http\Requests\Admin\Concerns\ChecksPaymentProof;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSaleRequest extends FormRequest
{
    use ChecksFinishedGoodsStock;
    use ChecksPaymentProof;

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $sale = $this->route('sale');
        $validator->after(fn () => $this->validateFinishedGoodsStock($validator, $sale instanceof Sale ? $sale : null));
        $validator->after(fn () => $this->validatePaymentProof($validator, $sale instanceof Sale ? $sale : null));
    }

    public function rules(): array
    {
        return $this->paymentProofRules() + [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_Phone' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'sale_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_status' => ['required', Rule::in(['Paid', 'Partially Paid', 'Pending', 'Credit'])],
            'delivery_status' => ['required', Rule::in(['Delivered', 'Pending', 'In Transit', 'Returned'])],
            'sales_channel' => ['required', Rule::in(['Momo Pay', 'Card', 'Cash', 'Online Store'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.production_id' => ['required', 'exists:productions,id'],
            'items.*.quantity_sold' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
