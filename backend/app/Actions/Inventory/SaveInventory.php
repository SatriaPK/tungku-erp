<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Enum\InventoryStatus;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveInventory
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $manager, ?Inventory $inventory = null): Inventory
    {
        return DB::transaction(function () use ($data, $manager, $inventory): Inventory {
            $lockedInventory = $inventory === null
                ? null
                : Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            $category = InventoryCategory::query()->lockForUpdate()->find($data['inventory_category_id']);
            if ($category === null || ! $category->is_active) {
                throw ValidationException::withMessages([
                    'inventory_category_id' => 'Kategori inventaris tidak ditemukan atau tidak aktif.',
                ]);
            }

            if (isset($data['room_id'])) {
                $roomIsActive = DB::table('rooms')->where('id', $data['room_id'])->value('is_active');
                if (! $roomIsActive) {
                    throw ValidationException::withMessages([
                        'room_id' => 'Ruangan tidak ditemukan atau tidak aktif.',
                    ]);
                }
            }

            if ($lockedInventory?->status->isGone()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Aset yang sudah dijual atau dibuang tidak dapat diubah.',
                ]);
            }

            $lockedInventory ??= new Inventory;
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($lockedInventory);
            $lockedInventory->fill([
                'code' => $data['code'],
                'name' => $data['name'],
                'inventory_category_id' => $category->id,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => $data['purchase_price'],
                'residual_value' => $data['residual_value'] ?? 0,
                'useful_life_months' => $data['useful_life_months'],
                'room_id' => $data['room_id'] ?? null,
                'expense_id' => $data['expense_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! $lockedInventory->exists) {
                $lockedInventory->status = InventoryStatus::Active;
                $lockedInventory->created_by = $manager->id;
            }

            $lockedInventory->save();
            $audit->handle($lockedInventory, $before === [] ? 'inventory.created' : 'inventory.updated', $before, AuditActor::user($manager));

            return $lockedInventory->load(['category', 'room', 'expense']);
        });
    }
}
