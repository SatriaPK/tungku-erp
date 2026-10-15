<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Gaji operator sengaja tidak ada di sini: gaji dibagi dari keuntungan
     * bersih, jadi mencatatnya sebagai pengeluaran akan menghitungnya dua kali.
     */
    public function run(): void
    {
        $categories = [
            'Sewa tempat',
            'Listrik dan internet',
            'Peralatan',
            'Perawatan dan servis',
            'Konsumsi',
            'Pemasaran',
            'Lain-lain',
        ];

        foreach ($categories as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
