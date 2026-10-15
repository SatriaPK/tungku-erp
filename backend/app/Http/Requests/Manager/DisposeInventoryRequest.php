<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DisposeInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:sold,disposed'],
            'disposed_on' => ['required', 'date_format:Y-m-d'],
            'disposal_value' => ['sometimes', 'required_if:type,sold', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('disposal_value');
            if (is_numeric($value) && (float) $value > PHP_INT_MAX) {
                $validator->errors()->add('disposal_value', 'Nilai melebihi kapasitas yang didukung.');
            }
        });
    }
}
