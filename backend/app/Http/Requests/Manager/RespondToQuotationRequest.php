<?php

namespace App\Http\Requests\Manager;

use App\Enum\QuotationResponseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Must be manager or higher (owner)
        return $this->user()?->isManager() ?? false;
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
