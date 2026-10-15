<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Expense\DeleteExpense;
use App\Actions\Expense\ListExpenses;
use App\Actions\Expense\SaveExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ExpenseRequest;
use App\Http\Resources\Manager\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function index(Request $request, ListExpenses $action): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'expense_category_id' => ['sometimes', 'integer', 'exists:expense_categories,id'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ExpenseResource::collection($action->handle($filters));
    }

    public function store(ExpenseRequest $request, SaveExpense $action): JsonResponse
    {
        $expense = $action->handle($request->validated(), $request->user());

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($expense->load(['category', 'recorder']));
    }

    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $action): ExpenseResource
    {
        return new ExpenseResource($action->handle($request->validated(), $request->user(), $expense));
    }

    public function destroy(Expense $expense, DeleteExpense $action): Response
    {
        $action->handle($expense);

        return response()->noContent();
    }
}
