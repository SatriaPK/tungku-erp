<?php

namespace App\Actions\Expense;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class DeleteExpense
{
    public function handle(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            app(EnsureExpensePeriodsAreOpen::class)->handle($expense->getRawOriginal('spent_on'));
            $expense->delete();
        });
    }
}
