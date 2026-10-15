<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudioSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Akses sudah dibatasi middleware role:manager di route
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $key = $this->route('key');

        return match ($key) {
            'po_percentage' => [
                'value' => ['required', 'numeric', 'min:30', 'max:70'],
            ],
            'studio_profit_percentage' => [
                'value' => ['required', 'numeric', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/', 'between:0,100'],
            ],
            'hold_duration_minutes' => [
                'value' => ['required', 'integer', 'min:10', 'max:30'],
            ],
            'operating_hours' => [
                'value' => ['required', 'json'],
            ],
            default => [
                'value' => ['required'],
            ],
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('key') === 'operating_hours' && ! $validator->errors()->has('value')) {
                $this->validateOperatingHoursFormat($validator);
            }
        });
    }

    private function validateOperatingHoursFormat(Validator $validator): void
    {
        $data = json_decode($this->input('value'), true);

        if (! is_array($data)) {
            $validator->errors()->add('value', 'Operating hours must be a valid JSON object.');

            return;
        }

        $requiredDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $missingDays = array_diff($requiredDays, array_keys($data));

        if (! empty($missingDays)) {
            $validator->errors()->add(
                'value',
                'Operating hours must include all 7 days. Missing: '.implode(', ', $missingDays)
            );

            return;
        }

        // Validate format HH:MM-HH:MM
        foreach ($data as $day => $hours) {
            if (! preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $hours)) {
                $validator->errors()->add(
                    'value',
                    "Invalid format for {$day}: expected HH:MM-HH:MM, got '{$hours}'."
                );

                return;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'The value field is required.',
            'value.numeric' => 'The value must be a number.',
            'value.integer' => 'The value must be an integer.',
            'value.json' => 'The value must be valid JSON.',
        ];
    }
}
