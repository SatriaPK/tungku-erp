<?php

namespace App\Actions\Booking;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Validation\ValidationException;

class CouponEligibility
{
    /** Caller must lock the parent before the child when reserving capacity. */
    public function discount(Coupon $code, Coupon $rule, int $subtotal, int $clientId, ?int $packageId, ?int $invoiceId = null): int
    {
        $fail = static fn (string $message) => throw ValidationException::withMessages(['coupon_code' => $message]);
        if (! $code->enabled || ! $rule->enabled) {
            $fail('Kupon sedang nonaktif.');
        }
        if ($code->kind === 'parent') {
            $fail('Gunakan kode unik yang dibagikan studio.');
        }
        if ($rule->validity_enabled && (now()->toDateString() < $rule->start || now()->toDateString() > $rule->end)) {
            $fail('Kupon di luar masa berlaku.');
        }
        if ($rule->minimum_enabled && $subtotal < $rule->minimum) {
            $fail('Minimum transaksi belum terpenuhi.');
        }
        $listed = $packageId !== null && in_array($packageId, array_map('intval', $rule->package_ids ?? []), true);
        if (($rule->package_mode === 'whitelist' && ! $listed) || ($rule->package_mode === 'blacklist' && $listed)) {
            $fail('Kupon tidak berlaku untuk paket ini.');
        }
        $redemptions = CouponRedemption::where('coupon_id', $code->id)
            ->when($invoiceId !== null, fn ($q) => $q->where('invoice_id', '!=', $invoiceId))
            ->where(function ($q): void {
                $q->where('status', 'redeemed')->orWhere(function ($q): void {
                    $q->where('status', 'reserved')->where('reserved_until', '>', now());
                });
            });
        // A locking read sees the latest committed reservations even under
        // MySQL REPEATABLE READ after waiting for the coupon row lock.
        $active = $redemptions->when($invoiceId !== null, fn ($q) => $q->lockForUpdate())->get(['id', 'client_id']);
        if ($code->kind === 'general') {
            if ($active->contains('client_id', $clientId)) {
                $fail('Klien sudah menggunakan atau mencadangkan kupon ini.');
            }
            if ($rule->quota_enabled && $active->count() >= $rule->limit) {
                $fail('Kuota kupon habis atau sedang dicadangkan.');
            }
        } elseif ($code->used > 0 || $active->isNotEmpty()) {
            $fail('Kode unik sudah digunakan atau sedang dicadangkan.');
        }
        $discount = $rule->type === 'percent' ? intdiv($subtotal * $rule->value, 100) : $rule->value;
        if ($rule->type === 'percent' && $rule->maximum_enabled && $rule->maximum > 0) {
            $discount = min($discount, $rule->maximum);
        }

        return max(0, min($discount, $subtotal - 1));
    }
}
