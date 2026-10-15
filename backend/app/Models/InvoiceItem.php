<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_number',
        'description',
        'details',
        'unit_price',
        'quantity',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'package_id',
        'recording_session_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * Hitung subtotal, tax, dan total berdasarkan unit_price dan quantity.
     * Dipanggil sebelum menyimpan item baru.
     */
    public static function calculateAmounts(
        int $unitPrice,
        int $quantity = 1,
        int $taxRate = 0,
        int $discountAmount = 0
    ): array {
        $subtotal = $unitPrice * $quantity;
        $taxAmount = (int) round($subtotal * $taxRate / 100);
        $total = $subtotal + $taxAmount - $discountAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => max(0, $total),
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function recordingSession(): BelongsTo
    {
        return $this->belongsTo(RecordingSession::class);
    }
}
