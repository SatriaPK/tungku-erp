<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackStageSubmission extends Model
{
    protected $fillable = [
        'track_stage_progress_id',
        'version',
        'result_url',
        'review_notes',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function progress(): BelongsTo
    {
        return $this->belongsTo(TrackStageProgress::class, 'track_stage_progress_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
