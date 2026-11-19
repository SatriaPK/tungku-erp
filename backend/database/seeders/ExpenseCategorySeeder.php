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
            'Sewa Studio',
            'Listrik dan Air',
            'Internet dan Telepon',
            'Perlengkapan Studio',
            'Perawatan Peralatan',
            'Kebersihan',
            'Konsumsi',
            'Pemasaran',
            'Lain-lain',
        ];

        foreach ($categories as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
