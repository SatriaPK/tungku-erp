<?php

namespace App\Http\Requests\Client;

use App\Enum\QuotationResponseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() ?? false;
    }

    public function rules(): array
    {
        return [
            'response_type' => ['required', Rule::enum(QuotationResponseType::class)],
            'latest_offer_id' => ['required', 'integer'],
            'price' => ['nullable', 'required_if:response_type,counter', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
