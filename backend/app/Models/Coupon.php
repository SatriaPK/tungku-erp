<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'type', 'value', 'minimum', 'maximum', 'start', 'end', 'limit', 'enabled'];

    protected $casts = ['value' => 'integer', 'minimum' => 'integer', 'maximum' => 'integer', 'limit' => 'integer', 'used' => 'integer', 'enabled' => 'boolean'];
}
