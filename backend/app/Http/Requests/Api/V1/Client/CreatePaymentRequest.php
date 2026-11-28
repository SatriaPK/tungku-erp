<?php

namespace App\Http\Requests\Api\V1\Client;

use App\Enum\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'type' => ['required', Rule::enum(PaymentType::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'in:xendit,manual,cash'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Project ID is required',
            'project_id.exists' => 'Project not found',
            'type.required' => 'Payment type is required',
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be at least 1',
            'provider.in' => 'Invalid payment provider',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Set default provider if not specified
        if (! isset($validated['provider'])) {
            $validated['provider'] = config('payment.default_gateway', 'cash');
        }

        return $key ? data_get($validated, $key, $default) : $validated;
    }
}
