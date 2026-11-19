<?php

namespace Database\Seeders;

use App\Models\StudioSetting;
use Illuminate\Database\Seeder;

class StudioSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'studio_profit_percentage',
                'value' => '50.00',
                'type' => 'decimal',
                'description' => 'Persentase laba bersih positif untuk Studio. Range: 0-100%.',
            ],
            [
                'key' => 'po_percentage',
                'value' => '50',
                'type' => 'decimal',
                'description' => 'Persentase pembayaran PO (Down Payment) dari total harga paket. Range: 30-70%.',
            ],
            [
                'key' => 'hold_duration_minutes',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Durasi slot recording ditahan (held) sebelum kedaluwarsa jika PO tidak dibayar. Range: 10-30 menit.',
            ],
            [
                'key' => 'operating_hours',
                'value' => json_encode([
                    'monday' => '09:00-22:00',
                    'tuesday' => '09:00-22:00',
                    'wednesday' => '09:00-22:00',
                    'thursday' => '09:00-22:00',
                    'friday' => '09:00-22:00',
                    'saturday' => '09:00-22:00',
                    'sunday' => '09:00-22:00',
                ]),
                'type' => 'json',
                'description' => 'Jam operasional studio per hari. Format: HH:MM-HH:MM.',
            ],
        ];

        foreach ($settings as $setting) {
            // firstOrCreate: jangan menimpa nilai yang sudah diubah Manager.
            StudioSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
