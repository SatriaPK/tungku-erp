<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OperatorActivitySeeder extends Seeder
{
    /**
     * Ledger hanya berasal dari aksi nyata; seeding tidak boleh membuat histori atau marker aktivasi.
     */
    public function run(): void {}
}
