<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Booking\ApplyBookingCoupon;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Quotation;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function preview(Request $request, ApplyBookingCoupon $action)
    {
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:24'],
            'package_id' => ['required_without:quotation_id', 'prohibits:quotation_id', 'integer'],
            'quotation_id' => ['required_without:package_id', 'prohibits:package_id', 'integer']]);
        if (isset($data['package_id'])) {
            $subtotal = Package::whereKey($data['package_id'])->where('is_active', true)->firstOrFail()->price;
        } else {
            $quote = Quotation::whereKey($data['quotation_id'])->where('client_id', $request->user()->id)->where('status', 'accepted')->firstOrFail();
            abort_if($quote->project()->exists(), 422, 'Penawaran sudah digunakan.');
            $subtotal = $quote->agreed_price;
        }
        $coupon = $action->find($data['coupon_code']);
        $discount = $action->discount($coupon, (int) $subtotal, $request->user()->id, $data['package_id'] ?? null);

        return response()->json(['data' => ['code' => $coupon->code, 'subtotal' => (int) $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount]]);
    }
}
