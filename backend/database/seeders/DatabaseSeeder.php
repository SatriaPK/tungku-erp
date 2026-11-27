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
            ReferenceDataSeeder::class,
            UserSeeder::class,
            OperatorSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(ManagerDashboardDemoSeeder::class);
        }
    }
}
