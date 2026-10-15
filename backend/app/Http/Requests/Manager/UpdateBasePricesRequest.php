<?php

namespace App\Http\Requests\Manager;

use App\Enum\StageType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBasePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bentuk input: { "prices": { "recording": 150000, "editing": 200000, ... } }
     * Kunci selain empat tahap ditolak, dan keempat kunci wajib ada.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $stages = StageType::values();

        $rules = [
            'prices' => ['required', 'array:'.implode(',', $stages)],
        ];

        foreach ($stages as $stage) {
            $rules["prices.{$stage}"] = ['required', 'integer', 'min:0', 'max:1000000000'];
        }

        return $rules;
    }
}
