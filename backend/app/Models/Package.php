<?php

namespace App\Models;

use App\Enum\StageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $price
 * @property int $auto_price
 * @property int|null $manual_price
 * @property-read Carbon|null $deleted_at
 */
class Package extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, string> */
    protected $casts = [
        'track_count' => 'integer',
        'recording_hours' => 'integer',
        'include_recording' => 'boolean',
        'include_editing' => 'boolean',
        'include_mixing' => 'boolean',
        'include_mastering' => 'boolean',
        'auto_price' => 'integer',
        'manual_price' => 'integer',
        'is_price_manual' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'description',
        'track_count',
        'recording_hours',
        'include_recording',
        'include_editing',
        'include_mixing',
        'include_mastering',
        'auto_price',
        'manual_price',
        'is_price_manual',
        'is_active',
    ];

    /**
     * Harga yang berlaku: harga manual kalau dipakai, selain itu harga otomatis.
     * Nilai inilah yang disalin ke package_snapshot dan projects.price.
     */
    protected function price(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->is_price_manual && $this->manual_price !== null
                ? $this->manual_price
                : $this->auto_price
        );
    }

    /**
     * Tahap yang aktif di paket ini, berurutan sesuai urutan produksi.
     *
     * @return array<int, StageType>
     */
    public function includedStages(): array
    {
        return array_values(array_filter(
            StageType::cases(),
            fn (StageType $stage) => (bool) $this->{$stage->includeColumn()},
        ));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CRMCampaign::class);
    }
}
