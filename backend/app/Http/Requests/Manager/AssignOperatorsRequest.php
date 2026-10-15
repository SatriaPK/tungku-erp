<?php

namespace App\Http\Requests\Manager;

use App\Enum\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Akses endpoint dibatasi middleware role:manager.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assignments = $this->input('assignments');

            if (! is_array($assignments)) {
                return;
            }

            $allowed = array_map(static fn (StageType $stage): string => $stage->value, StageType::cases());

            foreach (array_keys($assignments) as $stage) {
                if (! is_string($stage) || ! in_array($stage, $allowed, true)) {
                    $validator->errors()->add('assignments', 'Penugasan hanya menerima tahap produksi yang dikenal.');

                    return;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignments.required' => 'Daftar penugasan Operator wajib diisi.',
            'assignments.array' => 'Daftar penugasan harus berupa objek tahap dan ID Operator.',
            'assignments.min' => 'Sedikitnya satu tahap aktif harus ditugaskan.',
            'assignments.*.distinct' => 'Setiap tahap harus ditugaskan kepada Operator yang berbeda.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignments(): array
    {
        return $this->validated()['assignments'];
    }
}
