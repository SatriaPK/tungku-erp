<?php

namespace App\Actions\Expense;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveExpense
{
    /** @param array{expense_category_id: int, description: string, amount: int, spent_on: string} $data */
    public function handle(array $data, User $recorder, ?Expense $expense = null): Expense
    {
        return DB::transaction(function () use ($data, $recorder, $expense): Expense {
            $lockedExpense = $expense === null
                ? null
                : Expense::query()->lockForUpdate()->findOrFail($expense->id);

            app(EnsureExpensePeriodsAreOpen::class)->handle(
                $lockedExpense?->getRawOriginal('spent_on'),
                $data['spent_on'],
            );

            $category = ExpenseCategory::query()->lockForUpdate()->find($data['expense_category_id']);
            if ($category === null || ! $category->is_active) {
                throw ValidationException::withMessages([
                    'expense_category_id' => 'Kategori pengeluaran tidak ditemukan atau tidak aktif.',
                ]);
            }

            $lockedExpense ??= new Expense;
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($lockedExpense);
            $lockedExpense->fill([
                'expense_category_id' => $category->id,
                'recorded_by' => $lockedExpense->exists ? $lockedExpense->recorded_by : $recorder->id,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'spent_on' => $data['spent_on'],
            ]);
            $lockedExpense->save();
            $audit->handle($lockedExpense, $before === [] ? 'expense.created' : 'expense.updated', $before, AuditActor::user($recorder));

            return $lockedExpense->load(['category', 'recorder']);
        });
    }
}
