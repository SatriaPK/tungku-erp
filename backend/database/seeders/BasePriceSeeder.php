<?php

namespace Database\Seeders;

use App\Enum\PriceUnit;
use App\Enum\StageType;
use App\Models\BasePrice;
use Illuminate\Database\Seeder;

class BasePriceSeeder extends Seeder
{
    /**
     * Angka di bawah hanya nilai contoh. Manager mengubahnya dari aplikasi.
     * Seeder ini tidak menimpa harga yang sudah diubah Manager.
     */
    public function run(): void
    {
        $prices = [
            [StageType::Recording, PriceUnit::Hour, 150_000],
            [StageType::Editing, PriceUnit::Track, 200_000],
            [StageType::Mixing, PriceUnit::Track, 400_000],
            [StageType::Mastering, PriceUnit::Track, 250_000],
        ];

        foreach ($prices as [$stage, $unit, $price]) {
            BasePrice::firstOrCreate(
                ['stage' => $stage->value],
                ['unit' => $unit->value, 'price' => $price],
            );
        }
    }
}
