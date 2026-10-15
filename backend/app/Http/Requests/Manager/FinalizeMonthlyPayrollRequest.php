<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeMonthlyPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:1,9999'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
