<?php

namespace App\Models;

use App\Enum\OfferSide;
use App\Enum\QuotationStatus;
use App\Enum\StageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property QuotationStatus $status
 * @property string|null $name
 * @property Carbon|null $closed_at
 */
class Quotation extends Model
{
    /** @var array<string, string> */
    protected $casts = [
        'track_count' => 'integer',
        'recording_hours' => 'integer',
        'include_recording' => 'boolean',
        'include_editing' => 'boolean',
        'include_mixing' => 'boolean',
        'include_mastering' => 'boolean',
        'status' => QuotationStatus::class,
        'auto_price' => 'integer',
        'agreed_price' => 'integer',
        'closed_at' => 'datetime',
    ];

    protected $fillable = [
        'name',
        'client_id',
        'track_count',
        'recording_hours',
        'include_recording',
        'include_editing',
        'include_mixing',
        'include_mastering',
        'notes',
        'status',
        'auto_price',
        'agreed_price',
        'closed_by',
        'closed_at',
    ];

    /**
     * @return array<int, StageType>
     */
    public function includedStages(): array
    {
        return array_values(array_filter(
            StageType::cases(),
            fn (StageType $stage) => (bool) $this->{$stage->includeColumn()},
        ));
    }

    public function packageDisplayName(): string
    {
        return $this->name ?? 'Penawaran Kustom #'.$this->id;
    }

    /**
     * Apakah sekarang giliran pihak ini untuk bertindak.
     */
    public function isTurnOf(OfferSide $side): bool
    {
        return $this->status->awaitedSide() === $side;
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Riwayat tawaran dari yang pertama sampai terbaru.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(QuotationOffer::class)->orderBy('id');
    }

    public function latestOffer(): HasOne
    {
        return $this->hasOne(QuotationOffer::class)->latestOfMany();
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
