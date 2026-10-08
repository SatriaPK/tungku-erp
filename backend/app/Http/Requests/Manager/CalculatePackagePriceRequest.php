<?php

namespace App\Http\Requests\Manager;

use Illuminate\Support\Arr;

/**
 * Pratinjau harga untuk formulir paket: hanya spesifikasi, tanpa nama atau harga manual.
 */
class CalculatePackagePriceRequest extends PackageRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['name', 'description', 'manual_price', 'is_active']);
    }
}