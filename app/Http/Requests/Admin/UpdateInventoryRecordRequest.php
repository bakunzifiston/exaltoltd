<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'item_type' => ['required', Rule::in(['Product', 'Raw Material'])],
            'item_name' => ['required', 'string', 'max:255'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'supplier_lot_number' => ['nullable', 'string', 'max:100'],
            'inspection_status' => ['nullable', 'in:pending,approved,rejected'],
            'coa_reference' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['nullable', 'exists:products,id'],
            'quantity_in' => ['required', 'numeric', 'min:0'],
            'quantity_out' => ['required', 'numeric', 'min:0', 'lte:quantity_in'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'damaged' => ['required', 'numeric', 'min:0', 'lte:quantity_in'],
            'storage_location' => ['required', 'string', 'max:255'],
            'record_date' => ['required', 'date'],
            'total_amount' => ['nullable', 'numeric'],
            'amount_paid' => ['nullable', 'numeric'],
            'payment_status' => ['required', Rule::in(['Paid', 'Partial', 'Unpaid'])],
            'payment_due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_out.lte' => 'Quantity out cannot exceed quantity in — this would drop on-hand below what has been consumed.',
            'damaged.lte' => 'Damaged quantity cannot exceed quantity in.',
        ];
    }
}
