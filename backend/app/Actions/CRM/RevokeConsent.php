<?php

namespace App\Actions\CRM;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeConsent
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($user);
            $user->update(['crm_consent_at' => null]);
            $audit->handle($user, 'crm.consent_revoked', $before, AuditActor::user($user));
        });
    }
}
