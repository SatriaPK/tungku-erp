<?php

namespace App\Http\Resources\Manager;

use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inventory */
class InventoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'inventory_category_id' => $this->inventory_category_id,
            'category' => $this->whenLoaded('category', function () {
                /** @var InventoryCategory $category */
                $category = $this->category;

                return new InventoryCategoryResource($category);
            }),
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'purchase_date' => $this->purchase_date->toDateString(),
            'purchase_price' => $this->purchase_price,
            'residual_value' => $this->residual_value,
            'useful_life_months' => $this->useful_life_months,
            'monthly_depreciation' => $this->monthlyDepreciation(),
            'book_value' => $this->bookValue(),
            'book_value_as_of' => now()->toDateString(),
            'status' => $this->status->value,
            'disposed_on' => $this->disposed_on?->toDateString(),
            'disposal_value' => $this->disposal_value,
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', function () {
                /** @var Room|null $room */
                $room = $this->room;

                return $room === null ? null : ['id' => $room->id, 'name' => $room->name];
            }),
            'expense_id' => $this->expense_id,
            'expense' => $this->whenLoaded('expense', function () {
                /** @var Expense|null $expense */
                $expense = $this->expense;

                return $expense === null ? null : ['id' => $expense->id, 'description' => $expense->description];
            }),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
