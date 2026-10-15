<?php

namespace App\Http\Requests\Booking;

use App\Enum\QuotationStatus;
use App\Models\Package;
use App\Models\Project;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HoldSlotRequest extends FormRequest
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
            'package_id' => ['required_without:quotation_id', 'integer', 'exists:packages,id'],
            'quotation_id' => ['required_without:package_id', 'integer', 'exists:quotations,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('package_id') && $this->filled('quotation_id')) {
                $validator->errors()->add('quotation_id', 'Choose either a package or a quotation.');
            }
        }];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate duration constraints (same as CheckAvailability)
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

            // Validate package is active
            if ($this->has('package_id')) {
                $package = Package::find($this->input('package_id'));
                if ($package && ! $package->is_active) {
                    $validator->errors()->add(
                        'package_id',
                        'The selected package is not available.'
                    );
                }
            }

            // Validate quotation is accepted and belongs to user
            if ($this->has('quotation_id') && $this->input('quotation_id')) {
                $quotation = Quotation::find($this->input('quotation_id'));
                if ($quotation) {
                    if ($quotation->client_id !== $this->user()->id) {
                        $validator->errors()->add('quotation_id', 'The selected quotation does not belong to you.');
                    } elseif ($quotation->status !== QuotationStatus::Accepted) {
                        $validator->errors()->add('quotation_id', 'The selected quotation is not accepted.');
                    } elseif ($quotation->project()->exists()) {
                        $validator->errors()->add('quotation_id', 'The selected quotation has already been used.');
                    }
                }
            }

            // Validate project belongs to authenticated user
            if ($this->has('project_id') && $this->input('project_id')) {
                $project = Project::find($this->input('project_id'));
                if ($project && $project->client_id !== $this->user()->id) {
                    $validator->errors()->add(
                        'project_id',
                        'The selected project does not belong to you.'
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
            'package_id.required_without' => 'A package or accepted quotation is required.',
            'package_id.exists' => 'The selected package does not exist.',
            'project_id.exists' => 'The selected project does not exist.',
        ];
    }
}
