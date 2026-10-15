<?php

namespace App\Actions\InventoryCategory;

use App\Models\InventoryCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteInventoryCategory
{
    public function handle(InventoryCategory $category): void
    {
        try {
            DB::transaction(function () use ($category): void {
                $category = InventoryCategory::query()->lockForUpdate()->findOrFail($category->id);

                if ($category->inventories()->exists()) {
                    throw ValidationException::withMessages([
                        'category' => 'Kategori yang sudah digunakan pada inventaris tidak dapat dihapus; nonaktifkan kategori.',
                    ]);
                }

                $category->delete();
            });
        } catch (QueryException $exception) {
            // Keep the foreign-key constraint as the final guard against an
            // inventory being attached concurrently with this deletion.
            if (str_contains(strtolower($exception->getMessage()), 'inventory_category_id')) {
                throw ValidationException::withMessages([
                    'category' => 'Kategori yang sudah digunakan pada inventaris tidak dapat dihapus; nonaktifkan kategori.',
                ]);
            }

            throw $exception;
        }
    }
}
