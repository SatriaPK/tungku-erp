<?php

namespace App\Models;

use App\Enum\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceStatus $status
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property-read User|null $client
 * @property-read Project|null $project
 * @property-read RecordingSession|null $recordingSession
 * @property-read Payment|null $latestPayment
 */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /** @var array<string, string> */
    protected $casts = [
        'status' => InvoiceStatus::class,
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'discount_amount' => 'integer',
        'total' => 'integer',
        'metadata' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    protected $fillable = [
        'invoice_number',
        'client_id',
        'project_id',
        'recording_session_id',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'metadata',
        'issue_date',
        'due_date',
        'paid_at',
    ];

    /**
     * Invoice yang sudah diterbitkan dan belum dibatalkan.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::Paid->value,
        ]);
    }

    /**
     * Invoice yang sudah diterbitkan tetapi belum lunas.
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Issued->value);
    }

    /**
     * Invoice yang sudah lunas.
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Paid->value);
    }

    /**
     * Invoice yang lewat jatuh tempo dan belum lunas.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    /**
     * Apakah invoice ini sudah lewat jatuh tempo?
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Issued
            && $this->due_date !== null
            && Carbon::parse($this->due_date)->isPast();
    }

    /**
     * Hitung ulang total berdasarkan item-item invoice.
     * Berguna saat item ditambah/diubah.
     */
    public function recalculateTotal(): void
    {
        $itemTotals = $this->items()
            ->selectRaw('
                COALESCE(SUM(subtotal), 0) as subtotal,
                COALESCE(SUM(tax_amount), 0) as tax_amount,
                COALESCE(SUM(discount_amount), 0) as discount_amount,
                COALESCE(SUM(total), 0) as total
            ')
            ->toBase()
            ->first();

        $this->update([
            'subtotal' => (int) $itemTotals->subtotal,
            'tax_amount' => (int) $itemTotals->tax_amount,
            'discount_amount' => (int) $itemTotals->discount_amount,
            'total' => (int) $itemTotals->total,
        ]);
    }

    /**
     * Tandai invoice sebagai sudah dibayar.
     */
    public function markAsPaid(): bool
    {
        if (! $this->status->canTransitionTo(InvoiceStatus::Paid)) {
            return false;
        }

        return $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recordingSession(): BelongsTo
    {
        return $this->belongsTo(RecordingSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_number');
    }

    /**
     * Payment terkait invoice ini (jika ada).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
