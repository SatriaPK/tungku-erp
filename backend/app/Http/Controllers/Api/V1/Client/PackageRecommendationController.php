<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageCatalogResource;
use App\Models\Package;
use App\Services\PackageRecommendationModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PackageRecommendationController extends Controller
{
    public function __invoke(Request $request, PackageRecommendationModel $ml): JsonResponse
    {
        $input = $request->validate([
            'track_count' => ['required', 'integer', 'min:1', 'max:255'],
            'recording_hours' => ['required', 'integer', 'min:0', 'max:255'],
            'stages' => ['required', 'array', 'min:1', 'max:4'],
            'stages.*' => ['required', 'distinct', 'in:recording,editing,mixing,mastering'],
            'budget' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ]);
        if (in_array('recording', $input['stages'], true) && $input['recording_hours'] < 1) {
            throw ValidationException::withMessages([
                'recording_hours' => ['Durasi rekaman minimal satu jam.'],
            ]);
        }

        $catalog = $ml->catalog();
        $model = $ml->load($catalog);

        // Content-based matching: rank only packages that cover every required service.
        $results = $catalog->filter(function (Package $package) use ($input): bool {
            return $package->track_count >= $input['track_count']
                && (! in_array('recording', $input['stages'], true) || $package->recording_hours >= $input['recording_hours'])
                && collect($input['stages'])->every(fn (string $stage): bool => (bool) $package->{'include_'.$stage});
        })->map(function (Package $package) use ($input, $request, $model, $ml): array {
            $extraStages = count($package->includedStages()) - count($input['stages']);
            $trackFit = $input['track_count'] / $package->track_count;
            $hourFit = in_array('recording', $input['stages'], true)
                ? $input['recording_hours'] / max(1, $package->recording_hours) : 1;
            $budget = $input['budget'] ?? null;
            $withinBudget = $budget === null || $package->price <= $budget;
            $score = (int) round(100 * (0.5 / (1 + $extraStages) + 0.3 * $trackFit + 0.2 * $hourFit));

            if ($model !== null) {
                $score = $ml->score($package, $input, $model);
            }

            return [
                'package' => (new PackageCatalogResource($package))->resolve($request),
                'score' => $score,
                'within_budget' => $withinBudget,
                'reasons' => [
                    'Mencakup semua tahap produksi yang dipilih.',
                    "Kapasitas {$package->track_count} lagu untuk kebutuhan {$input['track_count']} lagu.",
                    in_array('recording', $input['stages'], true)
                        ? "Termasuk {$package->recording_hours} jam rekaman."
                        : 'Kebutuhan tanpa rekaman studio telah diperhitungkan.',
                    $budget === null ? 'Anggaran belum ditentukan.' : ($withinBudget ? 'Harga sesuai anggaran.' : 'Harga melebihi anggaran; pertimbangkan penawaran kustom.'),
                ],
            ];
        })->sort(function (array $a, array $b): int {
            return ($b['within_budget'] <=> $a['within_budget'])
                ?: ($b['score'] <=> $a['score'])
                ?: ($a['package']['price'] <=> $b['package']['price'])
                ?: ($a['package']['id'] <=> $b['package']['id']);
        })->take(3)->values();

        return response()->json([
            'data' => $results,
            'meta' => [
                'method' => $model === null ? 'content_based_fallback' : $model['method'],
                'trained_at' => $model['trained_at'] ?? null,
                'training_samples' => $model['report']['sample_count'] ?? 0,
                'data_source' => 'database_catalog_non_demo',
                'quality_evaluated' => false,
                'message' => $results->isEmpty() ? 'Belum ada paket yang memenuhi kebutuhan. Buat penawaran kustom.' : ($model === null ? 'Urutan berdasarkan anggaran dan kecocokan kebutuhan dengan katalog terbaru.' : 'Urutan berdasarkan anggaran dan kemiripan layanan, jumlah lagu, serta durasi. Skor bukan jaminan hasil produksi.'),
            ],
        ]);
    }
}
