<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCRMCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1', 'max:1'],
            'channels.*' => ['required', 'string', Rule::in(['email'])],
            'client_ids' => ['required', 'array', 'min:1', 'max:500'],
            'client_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }
}
