<?php

namespace App\Actions\Settings;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\StudioSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateStudioSetting
{
    public function handle(string $key, mixed $value, User $manager): StudioSetting
    {
        return DB::transaction(function () use ($key, $value, $manager): StudioSetting {
            $setting = StudioSetting::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($setting);
            $setting = StudioSetting::updateSetting($key, $value, $manager->id);
            $audit->handle($setting, 'setting.updated', $before, AuditActor::user($manager));

            return $setting;
        });
    }
}
