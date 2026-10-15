<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteInventory
{
    public function handle(Inventory $inventory): void
    {
        DB::transaction(function () use ($inventory): void {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if ($inventory->status->isGone()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Aset yang sudah dijual atau dibuang tidak dapat dihapus.',
                ]);
            }

            $inventory->delete();
        });
    }
}
