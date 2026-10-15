<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\InventoryCategory\DeleteInventoryCategory;
use App\Actions\InventoryCategory\SaveInventoryCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\InventoryCategoryRequest;
use App\Http\Resources\Manager\InventoryCategoryResource;
use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = InventoryCategory::query()
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(15);

        return InventoryCategoryResource::collection($categories);
    }

    public function store(InventoryCategoryRequest $request, SaveInventoryCategory $action): JsonResponse
    {
        $category = $action->handle($request->validated());

        return (new InventoryCategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(InventoryCategory $inventory_category): InventoryCategoryResource
    {
        return new InventoryCategoryResource($inventory_category);
    }

    public function update(
        InventoryCategoryRequest $request,
        InventoryCategory $inventory_category,
        SaveInventoryCategory $action,
    ): InventoryCategoryResource {
        return new InventoryCategoryResource($action->handle($request->validated(), $inventory_category));
    }

    public function destroy(InventoryCategory $inventory_category, DeleteInventoryCategory $action): Response
    {
        $action->handle($inventory_category);

        return response()->noContent();
    }
}
