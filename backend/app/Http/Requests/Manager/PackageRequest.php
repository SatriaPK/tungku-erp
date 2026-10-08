<?php

namespace App\Http\Requests\Manager;

use App\Enum\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Akses sudah dibatasi middleware role:manager di route
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'track_count' => ['required', 'integer', 'min:1', 'max:50'],
            'recording_hours' => ['required_if_accepted:include_recording', 'nullable', 'integer', 'min:1', 'max:100'],
            'include_recording' => ['required', 'boolean'],
            'include_editing' => ['required', 'boolean'],
            'include_mixing' => ['required', 'boolean'],
            'include_mastering' => ['required', 'boolean'],
            // Kosong berarti memakai harga otomatis
            'manual_price' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $anyStage = collect(StageType::cases())
                ->contains(fn (StageType $stage) => $this->boolean($stage->includeColumn()));

            if (! $anyStage) {
                $validator->errors()->add('stages', 'Pilih minimal satu tahap produksi.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recording_hours.required_if_accepted' => 'Jam Recording wajib diisi kalau tahap Recording aktif.',
            'track_count.min' => 'Jumlah track minimal 1.',
        ];
    }
}