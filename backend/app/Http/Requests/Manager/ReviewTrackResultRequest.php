<?php

namespace App\Http\Requests\Manager;

use App\Enum\TrackResultReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTrackResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(TrackResultReviewDecision::class)],
            'review_notes' => ['required_if:decision,request_revision', 'nullable', 'string', 'min:10', 'max:5000'],
        ];
    }
}
