<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatorStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
