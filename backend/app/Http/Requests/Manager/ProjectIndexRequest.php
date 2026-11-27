<?php

namespace App\Http\Requests\Manager;

use App\Enum\ProjectStatus;
use App\Enum\StageType;
use Illuminate\Foundation\Http\FormRequest;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:150'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'stage' => ['sometimes', 'string', 'in:'.implode(',', array_column(StageType::cases(), 'value'))],
            'sort' => ['sometimes', 'string', 'in:newest,oldest,name_asc,name_desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
