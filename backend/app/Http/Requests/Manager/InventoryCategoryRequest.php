<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $category = $this->route('inventory_category');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('inventory_categories', 'name')->ignore($category)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
