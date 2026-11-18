<?php

use App\Models\BasePrice;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\LegacyCatalogSeeder;

test('legacy import preserves source prices and skips demo packages', function () {
    User::factory()->manager()->create();
    $this->seed(LegacyCatalogSeeder::class);
    expect(BasePrice::where('stage', 'recording')->first()->price)->toBe(150000);
    expect(BasePrice::where('stage', 'mixing')->first()->price)->toBe(400000);
    expect(Package::count())->toBe(3);
    expect(Package::where('name', 'Paket Tungku Single')->first()->price)->toBe(1500000);
    expect(Package::where('name', 'Paket Tungku EP')->first()->price)->toBe(6500000);
    expect(Package::where('name', 'Paket Mixing Mastering')->first()->price)->toBe(2250000);
    expect(Package::where('name', 'like', 'DEMO%')->exists())->toBeFalse();
});

test('repeat imports never overwrite later manager price edits or duplicate packages', function () {
    User::factory()->manager()->create();
    $this->seed(LegacyCatalogSeeder::class);
    BasePrice::where('stage', 'recording')->update(['price' => 175000]);
    Package::where('name', 'Paket Tungku Single')->update(['auto_price' => 1800000]);
    $this->seed(LegacyCatalogSeeder::class);
    expect(BasePrice::where('stage', 'recording')->first()->price)->toBe(175000);
    expect(Package::where('name', 'Paket Tungku Single')->first()->price)->toBe(1800000);
    expect(Package::count())->toBe(3);
});
