<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageCatalogResource;
use App\Models\Package;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageCatalogController extends Controller
{
    /**
     * Get all active packages for the catalog.
     * Available to all authenticated users.
     */
    public function index(): AnonymousResourceCollection
    {
        $packages = Package::where('is_active', true)
            ->orderBy('name')
            ->get();

        return PackageCatalogResource::collection($packages);
    }
}
