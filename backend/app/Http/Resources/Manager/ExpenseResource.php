<?php

namespace App\Http\Resources\Manager;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_category_id' => $this->expense_category_id,
            'category' => $this->whenLoaded('category', function () {
                /** @var ExpenseCategory $category */
                $category = $this->category;

                return new ExpenseCategoryResource($category);
            }),
            'recorded_by' => $this->recorded_by,
            'recorder' => $this->whenLoaded('recorder', function () {
                /** @var User $recorder */
                $recorder = $this->recorder;

                return ['id' => $recorder->id, 'name' => $recorder->name];
            }),
            'description' => $this->description,
            'amount' => $this->amount,
            'spent_on' => $this->getRawOriginal('spent_on'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
