<?php

namespace App\Models;

use App\Enum\OperatorActivityType;
use App\Enum\StageType;
use Carbon\CarbonImmutable;
use Database\Factories\OperatorActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property OperatorActivityType $type
 * @property StageType $stage
 * @property CarbonImmutable|null $deadline_at
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $recorded_at
 */
class OperatorActivity extends Model
{
    /** @use HasFactory<OperatorActivityFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'type', 'source_id', 'actor_id', 'operator_id', 'project_id', 'project_stage_id',
        'project_track_id', 'submission_id', 'actor_name', 'operator_name', 'project_name',
        'track_title', 'stage', 'deadline_at', 'occurred_at', 'recorded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => OperatorActivityType::class, 'source_id' => 'integer', 'stage' => StageType::class,
            'deadline_at' => 'immutable_datetime', 'occurred_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (OperatorActivity $activity): never {
            throw new LogicException('Aktivitas operator tidak dapat diubah.');
        });
        static::deleting(function (OperatorActivity $activity): never {
            throw new LogicException('Aktivitas dan marker deduplikasi harus dipertahankan.');
        });
    }
}
