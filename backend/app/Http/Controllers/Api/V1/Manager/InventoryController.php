<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Inventory\DeleteInventory;
use App\Actions\Inventory\DisposeInventory;
use App\Actions\Inventory\SaveInventory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\DisposeInventoryRequest;
use App\Http\Requests\Manager\InventoryRequest;
use App\Http\Resources\Manager\InventoryResource;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,in_repair,sold,disposed'],
            'inventory_category_id' => ['sometimes', 'integer', 'exists:inventory_categories,id'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_deleted' => ['sometimes', 'boolean'],
        ]);

        $inventories = Inventory::query()
            ->with(['category', 'room', 'expense'])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['inventory_category_id']), fn ($query) => $query->where('inventory_category_id', $filters['inventory_category_id']))
            ->when(isset($filters['room_id']), fn ($query) => $query->where('room_id', $filters['room_id']))
            ->when(isset($filters['search']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('code', 'like', '%'.$filters['search'].'%')
                    ->orWhere('name', 'like', '%'.$filters['search'].'%');
            }))
            ->when($request->boolean('include_deleted'), fn ($query) => $query->withTrashed())
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return InventoryResource::collection($inventories);
    }

    public function store(InventoryRequest $request, SaveInventory $action): JsonResponse
    {
        $inventory = $action->handle($request->validated(), $request->user());

        return (new InventoryResource($inventory))->response()->setStatusCode(201);
    }

    public function show(Inventory $inventory): InventoryResource
    {
        return new InventoryResource($inventory->load(['category', 'room', 'expense']));
    }

    public function update(InventoryRequest $request, Inventory $inventory, SaveInventory $action): InventoryResource
    {
        return new InventoryResource($action->handle($request->validated(), $request->user(), $inventory));
    }

    public function destroy(Inventory $inventory, DeleteInventory $action): Response
    {
        $action->handle($inventory);

        return response()->noContent();
    }

    public function dispose(
        DisposeInventoryRequest $request,
        Inventory $inventory,
        DisposeInventory $action,
    ): InventoryResource {
        return new InventoryResource($action->handle($inventory, $request->validated()));
    }
}
