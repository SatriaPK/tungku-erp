<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'discount_amount' => 'integer',
        'rule_snapshot' => 'array',
        'reserved_until' => 'datetime',
        'redeemed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
