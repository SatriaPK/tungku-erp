<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** HTTP adapter only: training, filtering and scoring live in Python. */
class PackageRecommendationClient
{
    public function catalog(): Collection
    {
        return Package::active()->orderBy('id')->get();
    }

    private function payload(Collection $catalog): array
    {
        return $catalog->map(fn (Package $p): array => [
            'id' => $p->id, 'name' => $p->name, 'track_count' => $p->track_count,
            'recording_hours' => $p->recording_hours, 'price' => $p->price,
            'stages' => array_map(fn ($stage) => $stage->value, $p->includedStages()),
        ])->values()->all();
    }

    private function request(string $endpoint, array $payload): array
    {
        $url = rtrim((string) config('services.recommendation.url'), '/');
        if ($url === '') {
            throw new RuntimeException('Recommendation service is not configured.');
        }
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(25)
                ->post($url.$endpoint, $payload)->throw()->json();
        } catch (\Throwable $e) {
            throw new RuntimeException('Recommendation service is unavailable.', 0, $e);
        }
        if (! is_array($response) || ! is_array($response['meta'] ?? null)) {
            throw new RuntimeException('Invalid recommendation service response.');
        }

        return $response;
    }

    public function train(): array
    {
        return $this->request('/v1/train', ['catalog' => $this->payload($this->catalog())]);
    }

    public function recommend(Collection $catalog, array $input): array
    {
        $result = $this->request('/v1/recommend', ['catalog' => $this->payload($catalog), 'needs' => $input]);
        $rows = $result['data'] ?? null;
        if (! is_array($rows) || count($rows) > 3) {
            throw new RuntimeException('Invalid recommendation rows.');
        }
        $ids = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_int($row['package_id'] ?? null)
                || ! $catalog->contains('id', $row['package_id']) || in_array($row['package_id'], $ids, true)
                || ! is_int($row['score'] ?? null) || $row['score'] < 0 || $row['score'] > 100
                || ! is_bool($row['within_budget'] ?? null)) {
                throw new RuntimeException('Invalid recommendation row.');
            }
            $ids[] = $row['package_id'];
        }

        return $result;
    }
}
