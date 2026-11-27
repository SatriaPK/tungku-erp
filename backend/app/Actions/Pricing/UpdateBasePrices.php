<?php

namespace App\Actions\Pricing;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\BasePrice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBasePrices
{
    public function __construct(private readonly CalculatePackagePrice $calculator) {}

    /**
     * Memperbarui harga dasar, lalu menghitung ulang harga otomatis semua paket
     * dalam satu transaksi. Harga manual tidak disentuh, dan project yang sudah
     * berjalan tidak terpengaruh karena memakai snapshot.
     *
     * @param  array<string, int>  $prices  dikunci dengan nilai tahap: recording, editing, mixing, mastering
     */
    public function handle(User $manager, array $prices): void
    {
        DB::transaction(function () use ($manager, $prices) {
            foreach ($prices as $stage => $price) {
                BasePrice::query()->where('stage', $stage)->update([
                    'price' => $price,
                    'updated_by' => $manager->id,
                ]);
            }

            Package::query()->each(function (Package $package) use ($manager) {
                $audit = app(RecordBusinessChange::class);
                $before = $audit->snapshot($package);
                $package->auto_price = $this->calculator->handle(
                    $package->track_count,
                    $package->recording_hours,
                    $package->includedStages(),
                );
                $package->save();
                $audit->handle($package, 'package.price_recalculated', $before, AuditActor::user($manager));
            });
        });
    }
}
