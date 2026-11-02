<?php

namespace App\Actions\InventoryCategory;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\InventoryCategory;
use Illuminate\Support\Facades\DB;

class SaveInventoryCategory
{
    /** @param array{name: string, is_active?: bool} $data */
    public function handle(array $data, ?InventoryCategory $category = null, ?AuditActor $actor = null): InventoryCategory
    {
        return DB::transaction(function () use ($data, $category, $actor): InventoryCategory {
            $category ??= new InventoryCategory;
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($category);
            $category->fill([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? $category->is_active ?? true,
            ]);
            $category->save();
            $audit->handle($category, $before === [] ? 'inventory_category.created' : 'inventory_category.updated', $before, $actor ?? AuditActor::system(self::class));

            return $category;
        });
    }
}
