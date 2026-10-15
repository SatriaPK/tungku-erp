<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recording_session_id' => ['required', 'integer', 'exists:recording_sessions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recording_session_id.required' => 'Recording session ID is required.',
            'recording_session_id.exists' => 'The selected recording session does not exist.',
        ];
    }
}
