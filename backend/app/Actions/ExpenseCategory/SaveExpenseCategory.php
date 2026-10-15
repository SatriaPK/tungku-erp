<?php

namespace App\Actions\ExpenseCategory;

use App\Models\ExpenseCategory;

class SaveExpenseCategory
{
    /** @param array{name: string, is_active?: bool} $data */
    public function handle(array $data, ?ExpenseCategory $category = null): ExpenseCategory
    {
        $category ??= new ExpenseCategory;
        $category->fill([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? $category->is_active ?? true,
        ]);
        $category->save();

        return $category;
    }
}
