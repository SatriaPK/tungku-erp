<?php

namespace App\Actions\InventoryCategory;

use App\Models\InventoryCategory;

class SaveInventoryCategory
{
    /** @param array{name: string, is_active?: bool} $data */
    public function handle(array $data, ?InventoryCategory $category = null): InventoryCategory
    {
        $category ??= new InventoryCategory;
        $category->fill([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? $category->is_active ?? true,
        ]);
        $category->save();

        return $category;
    }
}
