<?php

namespace App\Actions\Booking;

use App\Enum\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponPaymentLifecycle
{
    public function reserve(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $snapshot = $invoice?->metadata['coupon'] ?? null;
        if (! isset($snapshot['coupon_id'])) {
            return;
        }
        DB::transaction(function () use ($payment, $invoice, $snapshot): void {
            $original = Coupon::findOrFail($snapshot['coupon_id']);
            $parent = $original->parent_id ? Coupon::whereKey($original->parent_id)->lockForUpdate()->firstOrFail() : null;
            $code = Coupon::whereKey($original->id)->lockForUpdate()->firstOrFail();
            $rule = $parent ?? $code;
            $discount = app(CouponEligibility::class)->discount($code, $rule, $snapshot['subtotal'], $invoice->client_id, $snapshot['package_id'] ?? null, $invoice->id);
            if ($discount !== (int) $snapshot['discount']) {
                throw ValidationException::withMessages(['coupon_code' => 'Aturan kupon berubah. Buat ulang checkout.']);
            }
            $existing = CouponRedemption::where('invoice_id', $invoice->id)->whereIn('status', ['reserved', 'redeemed'])->latest('id')->lockForUpdate()->first();
            if ($existing && ($existing->status === 'redeemed' || ($existing->status === 'reserved' && $existing->reserved_until?->isFuture() && $existing->payment_id !== $payment->id))) {
                throw ValidationException::withMessages(['coupon_code' => 'Tagihan sudah memiliki pembayaran kupon aktif.']);
            }
            CouponRedemption::updateOrCreate(['payment_id' => $payment->id], [
                'invoice_id' => $invoice->id,
                'coupon_id' => $code->id, 'client_id' => $invoice->client_id,
                'payment_id' => $payment->id, 'status' => 'reserved',
                'discount_amount' => $discount, 'rule_snapshot' => array_merge($rule->toArray(), ['code' => $code->code, 'parent_code' => $parent?->code]),
                'reserved_until' => $payment->expires_at, 'released_at' => null,
            ]);
        });
    }

    public function redeem(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran belum sukses.']);
        }
        DB::transaction(function () use ($payment): void {
            $row = CouponRedemption::where('payment_id', $payment->id)->first();
            if (! $row) {
                return;
            }
            $original = Coupon::findOrFail($row->coupon_id);
            $parent = $original->parent_id ? Coupon::whereKey($original->parent_id)->lockForUpdate()->firstOrFail() : null;
            $code = Coupon::whereKey($original->id)->lockForUpdate()->firstOrFail();
            $row = CouponRedemption::whereKey($row->id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'redeemed') {
                return;
            }
            if ($row->status !== 'reserved' || $row->reserved_until?->isPast()) {
                throw ValidationException::withMessages(['coupon_code' => 'Reservasi kupon telah berakhir. Pembayaran perlu ditinjau.']);
            }
            $row->update(['status' => 'redeemed', 'redeemed_at' => now()]);
            $code->increment('used');
            $parent?->increment('used');
        });
    }

    public function release(Payment $payment): void
    {
        CouponRedemption::where('payment_id', $payment->id)->where('status', 'reserved')
            ->update(['status' => 'released', 'released_at' => now()]);
    }
}
