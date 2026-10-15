<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class PayrollAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'year' => ['required', 'integer', 'between:1,9999'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['studio_profit_percentage'] = ['sometimes', 'required', 'numeric', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/', 'between:0,100'];
            $rules['allocations'] = ['required', 'array'];
            $rules['allocations.*.operator_id'] = ['required', 'integer', 'distinct', 'exists:users,id'];
            $rules['allocations.*.percentage'] = ['required', 'numeric', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/', 'between:0,100'];
        }

        return $rules;
    }
}
