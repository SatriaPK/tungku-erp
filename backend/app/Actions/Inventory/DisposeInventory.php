<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Enum\InventoryStatus;
use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisposeInventory
{
    /** @param array{type: string, disposed_on: string, disposal_value?: int|null} $data */
    public function handle(Inventory $inventory, array $data, ?AuditActor $actor = null): Inventory
    {
        return DB::transaction(function () use ($inventory, $data, $actor): Inventory {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            $targetStatus = match ($data['type']) {
                'sold' => InventoryStatus::Sold,
                'disposed' => InventoryStatus::Disposed,
                default => throw ValidationException::withMessages([
                    'type' => 'Jenis pelepasan harus sold atau disposed.',
                ]),
            };

            if (! in_array($targetStatus, $inventory->status->transitions(), true)) {
                throw ValidationException::withMessages([
                    'inventory' => 'Transisi status aset tidak diizinkan.',
                ]);
            }

            $disposedOn = $data['disposed_on'];
            if ($disposedOn < $inventory->purchase_date->toDateString()) {
                throw ValidationException::withMessages([
                    'disposed_on' => 'Tanggal pelepasan tidak boleh sebelum tanggal pembelian.',
                ]);
            }

            if ($targetStatus === InventoryStatus::Sold && ! array_key_exists('disposal_value', $data)) {
                throw ValidationException::withMessages([
                    'disposal_value' => 'Nilai penjualan wajib diisi untuk aset yang dijual.',
                ]);
            }

            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($inventory);
            $inventory->status = $targetStatus;
            $inventory->disposed_on = Carbon::createFromFormat('Y-m-d', $disposedOn)->startOfDay();
            $inventory->disposal_value = $targetStatus === InventoryStatus::Sold
                ? $data['disposal_value']
                : null;
            $inventory->save();
            $audit->handle($inventory, 'inventory.disposed', $before, $actor ?? AuditActor::system(self::class));

            return $inventory->load(['category', 'room', 'expense']);
        });
    }
}
