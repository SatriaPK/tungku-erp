<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Mikrofon',
            'Audio interface',
            'Monitor dan headphone',
            'Komputer dan perangkat',
            'Kabel dan aksesori',
            'Furnitur dan akustik ruangan',
            'Instrumen musik',
            'Pencahayaan studio',
            'Kamera dan video',
            'Perangkat lunak dan lisensi',
            'Penyimpanan dan media',
            'Daya dan kelistrikan',
        ];

        foreach ($categories as $name) {
            InventoryCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
