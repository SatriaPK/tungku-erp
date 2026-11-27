<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Package\SavePackage;
use App\Actions\Pricing\CalculatePackagePrice;
use App\Enum\StageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\CalculatePackagePriceRequest;
use App\Http\Requests\Manager\PackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PackageController extends Controller
{
    /**
     * Daftar paket. Tambahkan ?active=1 atau ?active=0 untuk menyaring.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $packages = Package::query()
            ->when(
                $request->has('active'),
                fn ($query) => $query->where('is_active', $request->boolean('active')),
            )
            ->latest('id')
            ->paginate(15);

        return PackageResource::collection($packages);
    }

    public function summary(): JsonResponse
    {
        $packages = Package::query();
        $activePackages = (clone $packages)->where('is_active', true);

        return response()->json([
            'data' => [
                'total' => (clone $activePackages)->count(),
                'by_stage' => collect(StageType::cases())->mapWithKeys(fn (StageType $stage): array => [
                    $stage->value => (clone $activePackages)->where($stage->includeColumn(), true)->count(),
                ]),
            ],
        ]);
    }

    public function store(PackageRequest $request, SavePackage $action): JsonResponse
    {
        $package = $action->handle($request->validated(), $request->user());

        return (new PackageResource($package))->response()->setStatusCode(201);
    }

    public function show(Package $package): PackageResource
    {
        return new PackageResource($package);
    }

    public function update(PackageRequest $request, Package $package, SavePackage $action): PackageResource
    {
        return new PackageResource(
            $action->handle($request->validated(), $request->user(), $package)
        );
    }

    /**
     * Soft delete. Project yang sudah berjalan memakai snapshot, jadi tidak terpengaruh.
     */
    public function destroy(Package $package): Response
    {
        $package->delete();

        return response()->noContent();
    }

    /**
     * Pratinjau harga otomatis untuk formulir, tanpa menyimpan apa pun.
     */
    public function calculatePrice(CalculatePackagePriceRequest $request, CalculatePackagePrice $calculator): JsonResponse
    {
        return response()->json([
            'data' => [
                'auto_price' => $calculator->handleFromInput($request->validated()),
            ],
        ]);
    }
}
