<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Coupon::latest()->get()]);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['limit' => 1, 'code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_-]{3,24}$/', Rule::unique('coupons')->ignore($coupon?->id), ...($coupon && $coupon->used > 0 ? [Rule::in([$coupon->code])] : [])],
            'name' => ['required', 'string', 'max:80'], 'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', 'max:'.($request->input('type') === 'percent' ? 100 : 1000000000)],
            'minimum' => ['required', 'integer', 'min:0', 'max:1000000000'], 'maximum' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'limit' => ['required', 'integer', 'in:1'], 'enabled' => ['required', 'boolean'],
        ]);
    }

    public function store(Request $request)
    {
        return response()->json(['data' => Coupon::create($this->validated($request))], 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        return DB::transaction(function () use ($request, $coupon) {
            $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $coupon->update($this->validated($request, $coupon));

            return response()->json(['data' => $coupon->fresh()]);
        });
    }
}
