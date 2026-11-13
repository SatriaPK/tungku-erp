<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/** Standardized, instance-based nearest-neighbor retrieval over the actual catalog. */
class PackageRecommendationModel
{
    public const FEATURES = ['track_count', 'recording_hours', 'recording', 'editing', 'mixing', 'mastering', 'log_price'];

    public function catalog(): Collection
    {
        return Package::active()->orderBy('id')->get()->filter(fn (Package $p) => ! preg_match('/\b(demo|dummy|test|testing|contoh)\b/i', $p->name)
            && $p->track_count > 0 && $p->recording_hours >= 0 && $p->price > 0 && count($p->includedStages()) > 0)->values();
    }

    public function vector(Package $p): array
    {
        return [(float) $p->track_count, (float) $p->recording_hours,
            (float) $p->include_recording, (float) $p->include_editing, (float) $p->include_mixing, (float) $p->include_mastering, log1p($p->price)];
    }

    public function fingerprint(Collection $catalog): string
    {
        return hash('sha256', json_encode($catalog->map(fn ($p) => [$p->id, $this->vector($p)])->all(), JSON_THROW_ON_ERROR));
    }

    public function path(): string
    {
        return storage_path('app/private/ml/package-neighbors.json');
    }

    public function train(): array
    {
        $catalog = $this->catalog();
        if ($catalog->count() < 2) {
            throw new \RuntimeException('Minimal dua paket katalog non-demo yang valid diperlukan. Model lama tidak diubah.');
        }
        $vectors = $catalog->map(fn ($p) => $this->vector($p))->all();
        $means = $scales = [];
        foreach (self::FEATURES as $i => $feature) {
            $column = array_column($vectors, $i);
            $means[$i] = array_sum($column) / count($column);
            $variance = array_sum(array_map(fn ($x) => ($x - $means[$i]) ** 2, $column)) / count($column);
            $scales[$i] = $variance > 0 ? sqrt($variance) : 1.0;
        }
        $model = ['schema' => 1, 'method' => 'standardized_nearest_neighbors', 'trained_at' => now()->toIso8601String(),
            'source' => 'database_catalog_non_demo', 'fingerprint' => $this->fingerprint($catalog),
            'features' => self::FEATURES, 'means' => $means, 'scales' => $scales,
            'samples' => $catalog->map(fn ($p) => ['package_id' => $p->id, 'vector' => $this->vector($p)])->all(),
            'report' => ['sample_count' => count($vectors), 'excluded_active_packages' => Package::active()->count() - count($vectors),
                'generated_rows' => 0, 'personal_data_fields' => 0, 'provenance' => 'Database records; non-demo names do not prove real-world provenance.',
                'evaluation' => 'No verified held-out client choices. Precision, recall and accuracy are unavailable.',
                'warnings' => $catalog->filter(fn ($p) => $p->include_recording && $p->recording_hours === 0)->map(fn ($p) => "Package {$p->id}: recording enabled but recording_hours is zero.")->values()->all()]];
        File::ensureDirectoryExists(dirname($this->path()));
        File::replace($this->path(), json_encode($model, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $model;
    }

    public function load(Collection $catalog): ?array
    {
        try {
            if (! is_file($this->path())) {
                return null;
            }
            $model = json_decode(File::get($this->path()), true, 512, JSON_THROW_ON_ERROR);
            if (($model['schema'] ?? null) !== 1 || ($model['features'] ?? null) !== self::FEATURES
                || ($model['fingerprint'] ?? '') !== $this->fingerprint($catalog)
                || count($model['means'] ?? []) !== 7 || count($model['scales'] ?? []) !== 7) {
                return null;
            }
            foreach ($model['scales'] as $scale) {
                if (! is_numeric($scale) || $scale <= 0 || ! is_finite((float) $scale)) {
                    return null;
                }
            }
            foreach ($model['means'] as $mean) {
                if (! is_numeric($mean) || ! is_finite((float) $mean)) {
                    return null;
                }
            }

            return $model;
        } catch (\Throwable) {
            return null;
        }
    }

    public function score(Package $package, array $input, array $model): int
    {
        $vector = [(float) $input['track_count'], (float) $input['recording_hours']];
        foreach (['recording', 'editing', 'mixing', 'mastering'] as $stage) {
            $vector[] = in_array($stage, $input['stages'], true) ? 1.0 : 0.0;
        }
        $vector[] = isset($input['budget']) ? log1p($input['budget']) : 0.0;
        $sample = $this->vector($package);
        $sum = 0.0;
        $dimensions = 0;
        foreach ($vector as $i => $value) {
            if ($i === 6 && ! isset($input['budget'])) {
                continue;
            }
            $sum += (($value - $model['means'][$i]) / $model['scales'][$i] - ($sample[$i] - $model['means'][$i]) / $model['scales'][$i]) ** 2;
            $dimensions++;
        }

        return (int) round(100 / (1 + sqrt($sum / $dimensions)));
    }
}
