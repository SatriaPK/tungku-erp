<?php

namespace App\Models;

use App\Enum\SessionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SessionStatus $status
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property Carbon|null $held_until
 * @property Carbon $created_at
 * @property-read Project|null $project
 * @property-read ProjectStage|null $stage
 * @property-read Package|null $package
 * @property-read User|null $client
 * @property-read int|null $client_id
 * @property-read int|null $package_id
 * @property-read int|null $project_id
 * @property-read int|null $project_stage_id
 * @property-read int|null $payment_id
 * @property-read int|null $room_id
 * @property-read SessionStatus $status
 */
class RecordingSession extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'status' => SessionStatus::class,
        'held_until' => 'datetime',
        'is_extension' => 'boolean',
        'extra_fee' => 'integer',
    ];

    protected $fillable = [
        'room_id',
        'project_id',
        'project_stage_id',
        'client_id',
        'payment_id',
        'package_id',
        'start_at',
        'end_at',
        'status',
        'held_until',
        'is_extension',
        'extra_fee',
        'requested_by',
    ];

    /**
     * Sesi yang sedang menghalangi slot: terkonfirmasi, atau ditahan
     * dengan batas waktu yang belum lewat. Hold kedaluwarsa tidak dihitung
     * walaupun statusnya belum diperbarui scheduler.
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->where(function (Builder $outer) {
            $outer->where('status', SessionStatus::Confirmed->value)
                ->orWhere(function (Builder $held) {
                    $held->where('status', SessionStatus::Held->value)
                        ->where('held_until', '>', now());
                });
        });
    }

    /**
     * Sesi yang rentang waktunya beririsan dengan rentang yang diberikan.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('start_at', '<', $end)
            ->where('end_at', '>', $start);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    /**
     * Pemegang hold atau booking.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
