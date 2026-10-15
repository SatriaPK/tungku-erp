<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Semua seeder aman dijalankan berulang kali (memakai updateOrCreate).
     */
    public function run(): void
    {
        $this->call([
            RoomSeeder::class,
            BasePriceSeeder::class,
            ExpenseCategorySeeder::class,
            InventoryCategorySeeder::class,
            StudioSettingSeeder::class,
            UserSeeder::class,
        ]);
    }
}
