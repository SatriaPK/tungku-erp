<?php

namespace App\Http\Requests\Booking;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('start_at') && $this->has('end_at')) {
                $start = Carbon::parse($this->input('start_at'), config('app.timezone'));
                $end = Carbon::parse($this->input('end_at'), config('app.timezone'));
                $durationMinutes = $start->diffInMinutes($end);

                // Minimum 1 hour
                if ($durationMinutes < 60) {
                    $validator->errors()->add(
                        'end_at',
                        'Booking duration must be at least 1 hour.'
                    );
                }

                // Maximum 12 hours
                if ($durationMinutes > 720) {
                    $validator->errors()->add(
                        'end_at',
                        'Booking duration cannot exceed 12 hours.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Room ID is required.',
            'room_id.exists' => 'The selected room does not exist.',
            'start_at.required' => 'Start time is required.',
            'start_at.date' => 'Start time must be a valid date.',
            'start_at.after' => 'Start time must be in the future.',
            'end_at.required' => 'End time is required.',
            'end_at.date' => 'End time must be a valid date.',
            'end_at.after' => 'End time must be after start time.',
        ];
    }
}
