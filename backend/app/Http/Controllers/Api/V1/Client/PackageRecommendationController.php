<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageCatalogResource;
use App\Services\PackageRecommendationClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PackageRecommendationController extends Controller
{
    public function __invoke(Request $request, PackageRecommendationClient $ml): JsonResponse
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
        try {
            $prediction = $ml->recommend($catalog, $input);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Layanan rekomendasi sedang tidak tersedia. Silakan coba lagi.'], 503);
        }
        $byId = $catalog->keyBy('id');
        $results = collect($prediction['data'])->map(function (array $row) use ($byId, $input, $request): array {
            $package = $byId->get($row['package_id']);
            $budget = $input['budget'] ?? null;
            $withinBudget = $row['within_budget'];
            $score = $row['score'];

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
        })->values();

        return response()->json([
            'data' => $results,
            'meta' => $prediction['meta'],
        ]);
    }
}
