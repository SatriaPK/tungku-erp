<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        $all = Coupon::latest()->get();
        $byId = $all->keyBy('id');

        return response()->json(['data' => $all->map(function (Coupon $coupon) use ($byId) {
            $data = $coupon->toArray();
            $parent = $coupon->parent_id ? $byId->get($coupon->parent_id) : null;
            if ($parent) {
                $data = array_merge($data, $parent->only(['type', 'value', 'minimum', 'maximum', 'start', 'end', 'minimum_enabled', 'maximum_enabled', 'validity_enabled', 'package_mode', 'package_ids']));
            }
            $data['effective_enabled'] = $coupon->enabled && (! $coupon->parent_id || ($parent?->enabled ?? false));
            $data['parent_name'] = $parent?->name;

            return $data;
        })]);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $request->mergeIfMissing(['kind' => $coupon?->kind ?? 'general', 'limit' => 1, 'minimum' => 0, 'maximum' => 0,
            'start' => '2000-01-01', 'end' => '2099-12-31', 'minimum_enabled' => false, 'maximum_enabled' => false,
            'validity_enabled' => false, 'quota_enabled' => false, 'package_mode' => 'all', 'package_ids' => []]);
        if (! $request->boolean('validity_enabled')) {
            $request->merge(['start' => '2000-01-01', 'end' => '2099-12-31']);
        }

        return $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_-]{3,24}$/', Rule::unique('coupons')->ignore($coupon?->id), ...($coupon && $coupon->used > 0 ? [Rule::in([$coupon->code])] : [])],
            'name' => ['required', 'string', 'max:80'], 'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', 'max:'.($request->input('type') === 'percent' ? 100 : 1000000000)],
            'minimum' => ['required', 'integer', 'min:0', 'max:1000000000'], 'maximum' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'limit' => ['required', 'integer', 'min:1', 'max:1000000'], 'enabled' => ['required', 'boolean'],
            'kind' => ['required', Rule::in($coupon ? [$coupon->kind] : ['general', 'parent'])],
            'minimum_enabled' => ['required', 'boolean'], 'maximum_enabled' => ['required', 'boolean'],
            'validity_enabled' => ['required', 'boolean'], 'quota_enabled' => ['required', 'boolean'],
            'package_mode' => ['required', Rule::in(['all', 'whitelist', 'blacklist'])],
            'package_ids' => ['present', 'array', 'max:1000'], 'package_ids.*' => ['integer', 'distinct', 'exists:packages,id'],
        ]);
    }

    public function store(Request $request)
    {
        return response()->json(['data' => Coupon::create($this->validated($request))], 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        return DB::transaction(function () use ($request, $coupon) {
            if ($coupon->parent_id) {
                Coupon::whereKey($coupon->parent_id)->lockForUpdate()->firstOrFail();
            }
            $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            if ($coupon->kind === 'child') {
                $coupon->update($request->validate(['enabled' => ['required', 'boolean']]));

                return response()->json(['data' => $coupon->fresh()]);
            }
            $coupon->update($this->validated($request, $coupon));

            return response()->json(['data' => $coupon->fresh()]);
        });
    }

    public function generate(Request $request, Coupon $coupon)
    {
        $data = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:100']]);

        return DB::transaction(function () use ($coupon, $data) {
            $parent = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            abort_unless($parent->kind === 'parent', 422, 'Pilih aturan induk kupon unik.');
            $children = [];
            for ($i = 0; $i < $data['count']; $i++) {
                $values = $parent->only(['name', 'type', 'value', 'minimum', 'maximum', 'start', 'end', 'package_mode', 'package_ids', 'minimum_enabled', 'maximum_enabled', 'validity_enabled']);
                $children[] = Coupon::create(array_merge($values, ['code' => 'TS-'.strtoupper(bin2hex(random_bytes(10))), 'kind' => 'child', 'parent_id' => $parent->id, 'limit' => 1, 'quota_enabled' => true, 'enabled' => true]));
            }

            return response()->json(['data' => $children], 201);
        });
    }

    public function history(Request $request)
    {
        $data = $request->validate(['coupon_id' => ['nullable', 'uuid']]);

        return response()->json(CouponRedemption::query()
            ->when($data['coupon_id'] ?? null, fn ($q, $id) => $q->where('coupon_id', $id))
            ->with('coupon:id,code,name,parent_id')->latest()->paginate(30));
    }
}
