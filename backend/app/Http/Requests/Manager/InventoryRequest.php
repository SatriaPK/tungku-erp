<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $inventory = $this->route('inventory');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('inventories', 'code')->ignore($inventory)],
            'name' => ['required', 'string', 'max:100'],
            'inventory_category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'brand' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'residual_value' => ['sometimes', 'integer', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:65535'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->integer('purchase_price') < $this->integer('residual_value', 0)) {
                $validator->errors()->add('residual_value', 'Nilai sisa tidak boleh melebihi harga beli.');
            }

            foreach (['purchase_price', 'residual_value'] as $field) {
                $value = $this->input($field);
                if (is_numeric($value) && (float) $value > PHP_INT_MAX) {
                    $validator->errors()->add($field, 'Nilai melebihi kapasitas yang didukung.');
                }
            }
        });
    }
}
