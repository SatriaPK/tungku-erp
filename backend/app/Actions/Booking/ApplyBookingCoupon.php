<?php

namespace App\Actions\Booking;

use App\Models\Coupon;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class ApplyBookingCoupon
{
    public function discount(Coupon $coupon, int $subtotal, ?int $clientId = null, ?int $packageId = null): int
    {
        $rule = $coupon->parent_id ? Coupon::findOrFail($coupon->parent_id) : $coupon;

        return app(CouponEligibility::class)->discount($coupon, $rule, $subtotal, $clientId ?? 0, $packageId);
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
    public function apply(Invoice $invoice, string $code, ?int $packageId = null): void
    {
        $coupon = $this->find($code, true);
        $subtotal = $invoice->total;
        $discount = $this->discount($coupon, $subtotal, $invoice->client_id, $packageId);
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
            'metadata' => array_merge($invoice->metadata ?? [], ['coupon' => ['code' => $coupon->code, 'discount' => $discount, 'subtotal' => $subtotal, 'package_id' => $packageId, 'coupon_id' => $coupon->id]])]);
        if ($invoice->project) {
            $invoice->project->update(['price' => $invoice->total, 'po_amount' => $invoice->total]);
        }

    }
}
