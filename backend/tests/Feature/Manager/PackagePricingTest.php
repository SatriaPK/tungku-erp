<?php

use App\Enum\PriceUnit;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Package;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Harga dasar diset eksplisit di sini supaya test tidak bergantung pada angka seeder.
 */
function setBasePrices(int $recording = 150_000, int $editing = 200_000, int $mixing = 400_000, int $mastering = 250_000): void
{
    $rows = [
        [StageType::Recording, PriceUnit::Hour, $recording],
        [StageType::Editing, PriceUnit::Track, $editing],
        [StageType::Mixing, PriceUnit::Track, $mixing],
        [StageType::Mastering, PriceUnit::Track, $mastering],
    ];

    foreach ($rows as [$stage, $unit, $price]) {
        BasePrice::updateOrCreate(
            ['stage' => $stage->value],
            ['unit' => $unit->value, 'price' => $price],
        );
    }
}

/**
 * Paket 3 track: Recording 4 jam, Editing, Mixing.
 * Dengan harga dasar bawaan helper: 4 x 150.000 + 3 x (200.000 + 400.000) = 2.400.000
 */
function packagePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Paket Single',
        'description' => 'Recording, editing, dan mixing untuk satu rilisan.',
        'track_count' => 3,
        'recording_hours' => 4,
        'include_recording' => true,
        'include_editing' => true,
        'include_mixing' => true,
        'include_mastering' => false,
        'manual_price' => null,
    ], $overrides);
}

beforeEach(function () {
    setBasePrices();
    Sanctum::actingAs(User::factory()->manager()->create());
});

// Akses

it('hanya manager yang boleh mengakses harga dasar dan paket', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/manager/packages')->assertForbidden();
    $this->getJson('/api/v1/manager/base-prices')->assertForbidden();

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->postJson('/api/v1/manager/packages', packagePayload())->assertForbidden();
    $this->putJson('/api/v1/manager/base-prices', ['prices' => []])->assertForbidden();
});

// Harga dasar

it('menampilkan empat harga dasar berurutan sesuai tahap produksi', function () {
    $this->getJson('/api/v1/manager/base-prices')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.stage', 'recording')
        ->assertJsonPath('data.3.stage', 'mastering');
});

it('mengubah harga dasar dan mencatat siapa yang mengubahnya', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $this->putJson('/api/v1/manager/base-prices', ['prices' => [
        'recording' => 175_000,
        'editing' => 210_000,
        'mixing' => 420_000,
        'mastering' => 260_000,
    ]])->assertOk()->assertJsonPath('data.0.price', 175_000);

    expect(BasePrice::where('stage', 'recording')->value('price'))->toEqual(175_000)
        ->and(BasePrice::where('stage', 'recording')->value('updated_by'))->toEqual($manager->id);
});

it('menolak harga dasar negatif', function () {
    $this->putJson('/api/v1/manager/base-prices', ['prices' => [
        'recording' => 150_000,
        'editing' => -1,
        'mixing' => 400_000,
        'mastering' => 250_000,
    ]])->assertUnprocessable()->assertJsonValidationErrors('prices.editing');
});

it('menolak tahap yang tidak dikenal pada harga dasar', function () {
    $this->putJson('/api/v1/manager/base-prices', ['prices' => [
        'recording' => 150_000,
        'editing' => 200_000,
        'mixing' => 400_000,
        'mastering' => 250_000,
        'distribusi' => 100_000,
    ]])->assertUnprocessable()->assertJsonValidationErrors('prices');
});

// Pratinjau dan pembuatan paket

it('menghitung pratinjau harga tanpa menyimpan paket', function () {
    $this->postJson('/api/v1/manager/packages/calculate-price', [
        'track_count' => 3,
        'recording_hours' => 4,
        'include_recording' => true,
        'include_editing' => true,
        'include_mixing' => true,
        'include_mastering' => false,
    ])->assertOk()->assertJsonPath('data.auto_price', 2_400_000);

    expect(Package::count())->toBe(0);
});

it('menghitung harga otomatis di server dan mengabaikan nilai dari input', function () {
    $this->postJson('/api/v1/manager/packages', packagePayload(['auto_price' => 1]))
        ->assertCreated()
        ->assertJsonPath('data.auto_price', 2_400_000)
        ->assertJsonPath('data.price', 2_400_000)
        ->assertJsonPath('data.is_price_manual', false);
});

it('memakai harga manual sebagai harga yang berlaku tanpa mengubah harga otomatis', function () {
    $this->postJson('/api/v1/manager/packages', packagePayload(['manual_price' => 2_000_000]))
        ->assertCreated()
        ->assertJsonPath('data.auto_price', 2_400_000)
        ->assertJsonPath('data.manual_price', 2_000_000)
        ->assertJsonPath('data.price', 2_000_000)
        ->assertJsonPath('data.is_price_manual', true);
});

it('mengabaikan jam Recording kalau tahap Recording tidak aktif', function () {
    // 3 track x (200.000 + 400.000) = 1.800.000
    $this->postJson('/api/v1/manager/packages', packagePayload([
        'include_recording' => false,
        'recording_hours' => 5,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.recording_hours', 0)
        ->assertJsonPath('data.auto_price', 1_800_000);
});

it('menolak paket tanpa satu pun tahap produksi', function () {
    $this->postJson('/api/v1/manager/packages', packagePayload([
        'include_recording' => false,
        'include_editing' => false,
        'include_mixing' => false,
        'include_mastering' => false,
    ]))->assertUnprocessable()->assertJsonValidationErrors('stages');
});

it('mewajibkan jam Recording kalau tahap Recording aktif', function () {
    $this->postJson('/api/v1/manager/packages', packagePayload(['recording_hours' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recording_hours');
});

// Pembaruan dan penghapusan

it('menghitung ulang harga saat paket diubah dan kembali ke otomatis kalau harga manual dikosongkan', function () {
    $id = $this->postJson('/api/v1/manager/packages', packagePayload(['manual_price' => 2_000_000]))
        ->assertCreated()
        ->json('data.id');

    // Mastering aktif: 2.400.000 + 3 x 250.000 = 3.150.000
    $this->putJson("/api/v1/manager/packages/{$id}", packagePayload([
        'include_mastering' => true,
        'manual_price' => null,
    ]))
        ->assertOk()
        ->assertJsonPath('data.auto_price', 3_150_000)
        ->assertJsonPath('data.price', 3_150_000)
        ->assertJsonPath('data.is_price_manual', false);
});

it('menghapus paket secara soft delete', function () {
    $id = $this->postJson('/api/v1/manager/packages', packagePayload())->assertCreated()->json('data.id');

    $this->deleteJson("/api/v1/manager/packages/{$id}")->assertNoContent();

    $this->assertSoftDeleted('packages', ['id' => $id]);
    $this->getJson('/api/v1/manager/packages')->assertJsonCount(0, 'data');
});

// Dampak perubahan harga dasar

it('menghitung ulang harga otomatis semua paket saat harga dasar berubah tanpa menyentuh harga manual', function () {
    $autoId = $this->postJson('/api/v1/manager/packages', packagePayload(['name' => 'Otomatis']))
        ->assertCreated()->json('data.id');
    $manualId = $this->postJson('/api/v1/manager/packages', packagePayload(['name' => 'Manual', 'manual_price' => 1_000_000]))
        ->assertCreated()->json('data.id');

    $this->putJson('/api/v1/manager/base-prices', ['prices' => [
        'recording' => 200_000,
        'editing' => 200_000,
        'mixing' => 400_000,
        'mastering' => 250_000,
    ]])->assertOk();

    // 4 jam x 200.000 + 3 track x (200.000 + 400.000) = 2.600.000
    expect(Package::find($autoId)->price)->toBe(2_600_000)
        ->and(Package::find($manualId)->price)->toBe(1_000_000)
        ->and(Package::find($manualId)->auto_price)->toBe(2_600_000);
});