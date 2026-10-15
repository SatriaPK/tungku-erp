<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses
{
    /**
     * @param  array{expense_category_id?: int, date_from?: string, date_to?: string, per_page?: int}  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Expense::query()
            ->with(['category', 'recorder'])
            ->when(isset($filters['expense_category_id']), fn (Builder $query) => $query->where('expense_category_id', $filters['expense_category_id']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('spent_on', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('spent_on', '<=', $filters['date_to']))
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
