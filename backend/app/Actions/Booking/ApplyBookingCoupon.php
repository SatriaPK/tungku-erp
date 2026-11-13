<?php

namespace App\Actions\Booking;

use App\Models\Coupon;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class ApplyBookingCoupon
{
    public function discount(Coupon $coupon, int $subtotal): int
    {
        $today = now()->toDateString();
        $message = ! $coupon->enabled ? 'Kupon sedang nonaktif.' : ($today < $coupon->start ? 'Kupon belum mulai berlaku.' : ($today > $coupon->end ? 'Kupon sudah kedaluwarsa.' : ($coupon->used >= 1 ? 'Kode kupon sudah digunakan.' : ($subtotal < $coupon->minimum ? 'Minimum transaksi kupon belum terpenuhi.' : null))));
        if ($message) {
            throw ValidationException::withMessages(['coupon_code' => $message]);
        }
        $discount = $coupon->type === 'percent' ? (int) floor($subtotal * $coupon->value / 100) : $coupon->value;
        if ($coupon->type === 'percent' && $coupon->maximum > 0) {
            $discount = min($discount, $coupon->maximum);
        }

        // Payment gateway requires a positive payable amount.
        return max(0, min($discount, $subtotal - 1));
    }

    public function find(string $code, bool $lock = false): Coupon
    {
        $query = Coupon::where('code', strtoupper(trim($code)));
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'Kode kupon tidak ditemukan.']);
        }

        return $coupon;
    }

    // Called inside HoldSlot's transaction before any payment can be created.
    public function apply(Invoice $invoice, string $code): void
    {
        $coupon = $this->find($code, true);
        $discount = $this->discount($coupon, $invoice->total);
        if ($discount < 1) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon tidak memberikan potongan untuk tagihan ini.']);
        }
        $remaining = $discount;
        foreach ($invoice->items()->orderBy('id')->get() as $item) {
            $part = min($remaining, $item->total);
            $item->update(['discount_amount' => $item->discount_amount + $part, 'total' => $item->total - $part]);
            $remaining -= $part;
        }
        $invoice->update(['discount_amount' => $invoice->discount_amount + $discount, 'total' => $invoice->total - $discount,
            'metadata' => array_merge($invoice->metadata ?? [], ['coupon' => ['code' => $coupon->code, 'discount' => $discount]])]);
        if ($invoice->project) {
            $invoice->project->update(['price' => $invoice->total, 'po_amount' => $invoice->total]);
        }
        $coupon->increment('used');
    }
}
