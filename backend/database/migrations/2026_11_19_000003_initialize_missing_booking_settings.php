<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use the existing StudioSettingSeeder defaults, without replacing
        // settings that the manager has already configured.
        $settings = [
            ['key' => 'hold_duration_minutes', 'value' => '15', 'type' => 'integer', 'description' => 'Durasi slot recording ditahan sebelum pembayaran, dalam menit.'],
            ['key' => 'operating_hours', 'value' => json_encode(array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '09:00-22:00')), 'type' => 'json', 'description' => 'Jam operasional studio per hari. Format: HH:MM-HH:MM.'],
        ];
        foreach ($settings as $setting) {
            if (! DB::table('studio_settings')->where('key', $setting['key'])->exists()) {
                DB::table('studio_settings')->insert(array_merge($setting, ['created_at' => now(), 'updated_at' => now()]));
            }
        }
    }

    public function down(): void
    {
        // Preserve operational configuration on rollback.
    }
};
