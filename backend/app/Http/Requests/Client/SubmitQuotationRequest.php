<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SubmitQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'track_count' => ['required', 'integer', 'min:1', 'max:255'],
            'recording_hours' => ['required', 'integer', 'min:0', 'max:255'],
            'include_recording' => ['required', 'boolean'],
            'include_editing' => ['required', 'boolean'],
            'include_mixing' => ['required', 'boolean'],
            'include_mastering' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'offer_price' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::trim($this->input('name'))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasIncludedStage = collect([
                'include_recording',
                'include_editing',
                'include_mixing',
                'include_mastering',
            ])->contains(fn (string $field): bool => $this->boolean($field));

            if (! $hasIncludedStage) {
                $validator->errors()->add(
                    'include_recording',
                    'Minimal satu tahap produksi (recording, editing, mixing, atau mastering) harus dipilih.'
                );
            }

            if ($this->boolean('include_recording') && (int) $this->input('recording_hours') < 1) {
                $validator->errors()->add('recording_hours', 'Jam Recording minimal 1 jika tahap Recording dipilih.');
            }

            if (! $this->boolean('include_recording') && (int) $this->input('recording_hours') !== 0) {
                $validator->errors()->add('recording_hours', 'Jam Recording harus 0 jika tahap Recording tidak dipilih.');
            }
        });
    }
}
