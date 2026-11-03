<?php

namespace App\Models;

use App\Enum\TrackProgressStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TrackProgressStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property-read ProjectStage|null $stage
 * @property-read ProjectTrack|null $track
 */
class TrackStageProgress extends Model
{
    /** @var array<string, string> */
    protected $casts = [
        'status' => TrackProgressStatus::class,
        'revision_count' => 'integer',
        'current_version' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Laravel akan menebak jamak menjadi track_stage_progresses,
     * jadi nama tabel ditulis eksplisit.
     */
    protected $table = 'track_stage_progress';

    protected $fillable = [
        'project_stage_id',
        'project_track_id',
        'status',
        'result_url',
        'review_notes',
        'revision_count',
        'current_version',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    /** @return BelongsTo<ProjectTrack, $this> */
    public function track(): BelongsTo
    {
        return $this->belongsTo(ProjectTrack::class, 'project_track_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Riwayat semua versi submit hasil kerja untuk progress ini.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(TrackStageSubmission::class, 'track_stage_progress_id');
    }
}
