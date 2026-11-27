<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data referensi wajib (ruangan, harga dasar, kategori, pengaturan) yang aman
 * dijalankan di produksi pada setiap deploy. Hanya membuat baris yang belum
 * ada; tidak menimpa perubahan Manager dan tidak membuat akun atau data demo.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoomSeeder::class,
            BasePriceSeeder::class,
            ExpenseCategorySeeder::class,
            InventoryCategorySeeder::class,
            StudioSettingSeeder::class,
        ]);
    }
}
