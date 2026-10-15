<?php

namespace App\Models;

use App\Enum\PayrollStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'period_year',
        'period_month',
        'total_revenue',
        'total_expense',
        'net_profit',
        'studio_profit_percentage',
        'studio_profit_amount',
        'status',
        'finalized_at',
        'finalized_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'total_revenue' => 'integer',
            'total_expense' => 'integer',
            'net_profit' => 'integer',
            'studio_profit_percentage' => 'decimal:2',
            'studio_profit_amount' => 'integer',
            'status' => PayrollStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
