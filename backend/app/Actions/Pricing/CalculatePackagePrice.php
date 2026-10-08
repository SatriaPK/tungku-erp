<?php

namespace App\Actions\Pricing;

use App\Enum\PriceUnit;
use App\Enum\StageType;
use App\Models\BasePrice;
use RuntimeException;

class CalculatePackagePrice
{
    /**
     * Harga = jam Recording x harga per jam
     *       + jumlah track x harga per track, untuk setiap tahap non-Recording yang aktif.
     *
     * Kalau nanti ada diskon atau pembulatan, ubah di sini saja.
     *
     * @param  array<int, StageType>  $stages
     */
    public function handle(int $trackCount, int $recordingHours, array $stages): int
    {
        $rates = BasePrice::query()->toBase()->pluck('price', 'stage');

        $total = 0;

        foreach ($stages as $stage) {
            $rate = $rates->get($stage->value)
                ?? throw new RuntimeException("Harga dasar untuk tahap {$stage->value} belum diatur.");

            $quantity = match ($stage->priceUnit()) {
                PriceUnit::Hour => $recordingHours,
                PriceUnit::Track => $trackCount,
            };

            $total += (int) $rate * $quantity;
        }

        return $total;
    }

    /**
     * Mengambil tahap yang aktif dari input berbentuk include_recording, include_editing, dan seterusnya.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, StageType>
     */
    public static function stagesFrom(array $input): array
    {
        return array_values(array_filter(
            StageType::cases(),
            fn (StageType $stage) => (bool) ($input[$stage->includeColumn()] ?? false),
        ));
    }

    /**
     * Hitung langsung dari input spesifikasi paket.
     * Jam Recording diabaikan kalau tahap Recording tidak aktif.
     *
     * @param  array<string, mixed>  $input
     */
    public function handleFromInput(array $input): int
    {
        $stages = self::stagesFrom($input);

        $hours = in_array(StageType::Recording, $stages, true)
            ? (int) ($input['recording_hours'] ?? 0)
            : 0;

        return $this->handle((int) $input['track_count'], $hours, $stages);
    }
}