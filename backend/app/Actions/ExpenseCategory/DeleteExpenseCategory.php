<?php

namespace App\Actions\ExpenseCategory;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteExpenseCategory
{
    public function handle(ExpenseCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $category = ExpenseCategory::query()->lockForUpdate()->findOrFail($category->id);

            if ($category->expenses()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Kategori yang sudah digunakan pada pengeluaran tidak dapat dihapus; nonaktifkan kategori.',
                ]);
            }

            $category->delete();
        });
    }
}
