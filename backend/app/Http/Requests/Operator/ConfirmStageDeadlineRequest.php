<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmStageDeadlineRequest extends FormRequest
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
            'deadline_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deadline_at.required' => 'Tenggat tahap wajib diisi.',
            'deadline_at.date' => 'Tenggat harus berupa tanggal dan waktu yang valid.',
            'deadline_at.after' => 'Tenggat harus berada di masa depan.',
        ];
    }
}
