<?php

use App\Models\Package;
use App\Models\User;
use App\Services\PackageRecommendationModel;

beforeEach(function () {
    $this->modelPath = storage_path('framework/testing/package-ml-'.bin2hex(random_bytes(8)).'.json');
    $path = $this->modelPath;
    $this->ml = new class($path) extends PackageRecommendationModel
    {
        public function __construct(private string $file) {}

        public function path(): string
        {
            return $this->file;
        }
    };
    app()->instance(PackageRecommendationModel::class, $this->ml);
});
afterEach(function () {
    if (is_file($this->modelPath)) {
        unlink($this->modelPath);
    }
});

test('training excludes demo and inactive data and creates a loadable private artifact', function () {
    Package::factory()->create(['name' => 'Studio Single', 'track_count' => 1, 'auto_price' => 1000000]);
    Package::factory()->create(['name' => 'Studio EP', 'track_count' => 5, 'auto_price' => 5000000]);
    Package::factory()->create(['name' => 'DEMO Package']);
    Package::factory()->inactive()->create(['name' => 'Archived']);
    $model = $this->ml->train();
    expect($model['report']['sample_count'])->toBe(2)->and($model['report']['generated_rows'])->toBe(0)
        ->and($model['report']['personal_data_fields'])->toBe(0)->and($model['scales'][0])->toBe(2.0);
    expect($this->ml->load($this->ml->catalog()))->not->toBeNull();
});
test('API uses trained model and invalidates it after a catalog change', function () {
    $exact = Package::factory()->create(['name' => 'Exact Studio', 'track_count' => 2, 'recording_hours' => 4, 'auto_price' => 1500000, 'include_editing' => false, 'include_mastering' => false]);
    Package::factory()->create(['name' => 'Bigger Studio', 'track_count' => 5, 'recording_hours' => 10, 'auto_price' => 1900000]);
    $this->ml->train();
    $url = '/api/v1/client/package-recommendations?'.http_build_query(['track_count' => 2, 'recording_hours' => 4, 'stages' => ['recording', 'mixing'], 'budget' => 1500000]);
    $this->actingAs(User::factory()->client()->create())->getJson($url)->assertOk()
        ->assertJsonPath('meta.method', 'standardized_nearest_neighbors')->assertJsonPath('meta.quality_evaluated', false)
        ->assertJsonPath('data.0.package.id', $exact->id)->assertJsonPath('data.0.score', 100);
    $exact->update(['auto_price' => 1600000]);
    $this->getJson($url)->assertOk()->assertJsonPath('meta.method', 'content_based_fallback');
});
test('insufficient data never produces a pretend trained model', function () {
    Package::factory()->create(['name' => 'DEMO Only']);
    expect(fn () => $this->ml->train())->toThrow(RuntimeException::class);
    expect(is_file($this->modelPath))->toBeFalse();
});
