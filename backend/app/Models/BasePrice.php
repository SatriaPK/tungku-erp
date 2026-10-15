<?php

namespace App\Models;

use App\Enum\PriceUnit;
use App\Enum\StageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasePrice extends Model
{
    /** @var array<string, string> */
    protected $casts = [
        'stage' => StageType::class,
        'unit' => PriceUnit::class,
        'price' => 'integer',
    ];

    protected $fillable = [
        'stage',
        'unit',
        'price',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
