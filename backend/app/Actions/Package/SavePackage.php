<?php

namespace App\Actions\Package;

use App\Actions\Pricing\CalculatePackagePrice;
use App\Enum\StageType;
use App\Models\Package;
use App\Models\User;

class SavePackage
{
    public function __construct(private readonly CalculatePackagePrice $calculator) {}

    /**
     * Membuat paket baru, atau memperbarui paket yang diberikan.
     * Harga otomatis selalu dihitung server, tidak pernah diambil dari input.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $manager, ?Package $package = null): Package
    {
        $stages = CalculatePackagePrice::stagesFrom($data);

        $recordingHours = in_array(StageType::Recording, $stages, true)
            ? (int) $data['recording_hours']
            : 0;

        $manualPrice = $data['manual_price'] ?? null;

        $package ??= new Package;

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'track_count' => (int) $data['track_count'],
            'recording_hours' => $recordingHours,
            'auto_price' => $this->calculator->handleFromInput($data),
            'manual_price' => $manualPrice,
            'is_price_manual' => $manualPrice !== null,
            'is_active' => (bool) ($data['is_active'] ?? $package->is_active ?? true),
        ];

        foreach (StageType::cases() as $stage) {
            $attributes[$stage->includeColumn()] = in_array($stage, $stages, true);
        }

        $package->fill($attributes);

        if (! $package->exists) {
            $package->created_by = $manager->id;
        }

        $package->save();

        return $package;
    }
}
