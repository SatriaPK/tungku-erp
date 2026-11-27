<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PaymentType $type
 * @property PaymentStatus $status
 * @property PaymentProvider $provider
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project|null $project
 * @property-read Invoice|null $invoice
 * @property-read User|null $client
 */
class Payment extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'type' => PaymentType::class,
        'amount' => 'integer',
        'status' => PaymentStatus::class,
        'provider' => PaymentProvider::class,
        'raw_payload' => 'array',
        'gateway_response' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $fillable = [
        'project_id',
        'invoice_id',
        'type',
        'amount',
        'status',
        'provider',
        'order_id',
        'gateway_charge_id',
        'provider_transaction_id',
        'payment_method',
        'snap_token',
        'payment_url',
        'notes',
        'gateway_response',
        'raw_payload',
        'expires_at',
        'paid_at',
    ];

    /**
     * Payload mentah dan token tidak ikut terkirim ke frontend.
     *
     * @var list<string>
     */
    protected $hidden = [
        'raw_payload',
    ];

    public function scopePaid(Builder $query): void
    {
        $query->where('status', PaymentStatus::Paid->value);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Recording sessions yang dibayar dengan payment ini.
     */
    /** @return HasMany<RecordingSession, $this> */
    public function recordingSessions(): HasMany
    {
        return $this->hasMany(RecordingSession::class);
    }
}
