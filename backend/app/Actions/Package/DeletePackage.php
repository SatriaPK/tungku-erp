<?php

namespace App\Actions\Package;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletePackage
{
    public function handle(Package $package, User $manager): void
    {
        DB::transaction(function () use ($package, $manager): void {
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($package);
            $package->delete();
            $audit->handle($package, 'package.deleted', $before, AuditActor::user($manager), deleted: true);
        });
    }
}
