<?php

namespace App\Actions\ExpenseCategory;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

class SaveExpenseCategory
{
    /** @param array{name: string, is_active?: bool} $data */
    public function handle(array $data, ?ExpenseCategory $category = null, ?AuditActor $actor = null): ExpenseCategory
    {
        return DB::transaction(function () use ($data, $category, $actor): ExpenseCategory {
            $category ??= new ExpenseCategory;
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($category);
            $category->fill([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? $category->is_active ?? true,
            ]);
            $category->save();
            $audit->handle($category, $before === [] ? 'category.created' : 'category.updated', $before, $actor ?? AuditActor::system(self::class));

            return $category;
        });
    }
}
