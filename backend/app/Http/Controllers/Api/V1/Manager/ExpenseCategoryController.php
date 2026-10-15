<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\ExpenseCategory\DeleteExpenseCategory;
use App\Actions\ExpenseCategory\SaveExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ExpenseCategoryRequest;
use App\Http\Resources\Manager\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = ExpenseCategory::query()
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(15);

        return ExpenseCategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseCategoryRequest $request, SaveExpenseCategory $action): JsonResponse
    {
        $category = $action->handle($request->validated());

        return (new ExpenseCategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseCategory $expense_category): ExpenseCategoryResource
    {
        return new ExpenseCategoryResource($expense_category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expense_category, SaveExpenseCategory $action): ExpenseCategoryResource
    {
        return new ExpenseCategoryResource($action->handle($request->validated(), $expense_category));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $expense_category, DeleteExpenseCategory $action): Response
    {
        $action->handle($expense_category);

        return response()->noContent();
    }
}
