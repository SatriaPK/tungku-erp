<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $category = $this->route('expense_category');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('expense_categories', 'name')->ignore($category)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
