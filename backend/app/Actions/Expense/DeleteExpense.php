<?php

namespace App\Actions\Expense;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class DeleteExpense
{
    public function handle(Expense $expense, ?AuditActor $actor = null): void
    {
        DB::transaction(function () use ($expense, $actor): void {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            app(EnsureExpensePeriodsAreOpen::class)->handle($expense->getRawOriginal('spent_on'));
            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($expense);
            $expense->delete();
            $audit->handle($expense, 'expense.deleted', $before, $actor ?? AuditActor::system(self::class), deleted: true);
        });
    }
}
