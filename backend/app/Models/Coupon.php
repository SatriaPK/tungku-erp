<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'type', 'value', 'minimum', 'maximum', 'start', 'end', 'limit', 'enabled', 'kind', 'parent_id', 'package_mode', 'package_ids', 'minimum_enabled', 'maximum_enabled', 'validity_enabled', 'quota_enabled'];

    protected $casts = ['value' => 'integer', 'minimum' => 'integer', 'maximum' => 'integer', 'limit' => 'integer', 'used' => 'integer', 'enabled' => 'boolean', 'package_ids' => 'array', 'minimum_enabled' => 'boolean', 'maximum_enabled' => 'boolean', 'validity_enabled' => 'boolean', 'quota_enabled' => 'boolean'];
}
