<?php

namespace App\Actions\CRM;

use App\Enum\PaymentStatus;
use App\Enum\Role;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetClientRecommendations
{
    private const INACTIVE_DAYS = 90;

    public function handle(int $perPage): LengthAwarePaginator
    {
        $cutoff = CarbonImmutable::now(config('app.timezone'))->subDays(self::INACTIVE_DAYS);
        $paidHistory = Payment::query()
            ->selectRaw('MAX(payments.paid_at)')
            ->join('projects', 'projects.id', '=', 'payments.project_id')
            ->whereColumn('projects.client_id', 'users.id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->whereNotNull('payments.paid_at');

        $latestPaidAt = (clone $paidHistory)->toBase();

        return User::query()
            ->select('users.*')
            ->addSelect(['last_paid_at' => clone $paidHistory])
            ->where('role', Role::Client->value)
            ->where('is_active', true)
            ->whereNotNull('crm_consent_at')
            ->whereNotNull('email')
            ->where('email', 'like', '%@%')
            ->whereRaw("instr(substr(email, instr(email, '@') + 1), '.') > 1")
            ->whereExists(clone $paidHistory)
            ->whereRaw('('.$latestPaidAt->toSql().') <= ?', [
                ...$latestPaidAt->getBindings(),
                $cutoff->toDateTimeString(),
            ])
            ->orderBy('last_paid_at')
            ->orderBy('users.id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
