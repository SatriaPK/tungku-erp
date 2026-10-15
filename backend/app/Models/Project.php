<?php

namespace App\Models;

use App\Enum\PaymentStatus;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Enum\TrackProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read User|null $client
 * @property ProjectStatus $status
 * @property int $price
 * @property int $po_amount
 * @property int|null $final_payment_amount
 * @property Carbon|null $completed_at
 * @property-read Package|null $package
 * @property-read Quotation|null $quotation
 */
class Project extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'package_snapshot' => 'array',
        'price' => 'integer',
        'po_amount' => 'integer',
        'final_payment_amount' => 'integer',
        'status' => ProjectStatus::class,
        'completed_at' => 'datetime',
    ];

    protected $fillable = [
        'code',
        'client_id',
        'package_id',
        'quotation_id',
        'name',
        'package_snapshot',
        'price',
        'po_amount',
        'final_payment_amount',
        'status',
        'completed_at',
    ];

    /**
     * Project yang PO-nya sudah lunas dan belum berakhir.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            ProjectStatus::AwaitingAssignment->value,
            ProjectStatus::InProgress->value,
            ProjectStatus::AwaitingFinalPayment->value,
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(ProjectTrack::class)->orderBy('position');
    }

    /** @return HasMany<ProjectStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('sequence');
    }

    /**
     * Tahap aktif pertama yang belum selesai.
     */
    public function currentStage(): HasOne
    {
        return $this->hasOne(ProjectStage::class)->ofMany(
            ['sequence' => 'min'],
            function (Builder $query) {
                $query->where('status', '!=', StageStatus::Completed->value);
            },
        );
    }

    /**
     * Tahap terakhir dalam paket. Tautan hasilnya adalah hasil akhir untuk klien.
     */
    public function finalStage(): HasOne
    {
        return $this->hasOne(ProjectStage::class)->ofMany('sequence', 'max');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function recordingSessions(): HasMany
    {
        return $this->hasMany(RecordingSession::class);
    }

    /**
     * Total biaya perpanjangan dari sesi tambahan yang sah.
     */
    public function extensionFeeTotal(): int
    {
        return (int) $this->recordingSessions()
            ->where('is_extension', true)
            ->whereIn('status', [
                SessionStatus::Confirmed->value,
                SessionStatus::Completed->value,
            ])
            ->sum('extra_fee');
    }

    /**
     * Harga paket ditambah biaya perpanjangan.
     */
    public function totalDue(): int
    {
        return $this->price + $this->extensionFeeTotal();
    }

    public function paidAmount(): int
    {
        return (int) $this->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');
    }

    /**
     * Nominal pelunasan: total tagihan dikurangi yang sudah dibayar.
     */
    public function remainingAmount(): int
    {
        return max(0, $this->totalDue() - $this->paidAmount());
    }

    /**
     * Persentase progres produksi: sel track x tahap yang sudah disetujui
     * dibagi seluruh sel. Dihitung saat dibaca, tidak disimpan.
     */
    public function progressPercent(): int
    {
        $includedStages = $this->includedStageValues();

        if ($includedStages === []) {
            return 0;
        }

        $stats = TrackStageProgress::query()
            ->whereHas('stage', fn (Builder $query) => $query
                ->where('project_id', $this->id)
                ->whereIn('stage', $includedStages))
            ->selectRaw(
                'COUNT(*) as total, COALESCE(SUM(status = ?), 0) as approved',
                [TrackProgressStatus::Approved->value],
            )
            ->toBase()
            ->first();

        if ($stats === null || (int) $stats->total === 0) {
            return 0;
        }

        return (int) round(((int) $stats->approved) / (int) $stats->total * 100);
    }

    /**
     * @return array<int, string>
     */
    public function includedStageValues(): array
    {
        $snapshot = $this->getAttribute('package_snapshot') ?? [];

        if (isset($snapshot['stages']) && is_array($snapshot['stages'])) {
            return array_values(array_unique(array_map(
                static fn (string|StageType $stage): string => $stage instanceof StageType ? $stage->value : $stage,
                $snapshot['stages'],
            )));
        }

        return array_values(array_map(
            static fn (StageType $stage): string => $stage->value,
            array_filter(
                StageType::cases(),
                static fn (StageType $stage): bool => (bool) ($snapshot[$stage->includeColumn()] ?? false),
            ),
        ));
    }
}
