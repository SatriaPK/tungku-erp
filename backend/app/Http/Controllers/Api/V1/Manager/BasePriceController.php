<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Pricing\UpdateBasePrices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateBasePricesRequest;
use App\Http\Resources\BasePriceResource;
use App\Models\BasePrice;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BasePriceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $prices = BasePrice::query()
            ->get()
            ->sortBy(fn (BasePrice $price) => $price->stage->sequence())
            ->values();

        return BasePriceResource::collection($prices);
    }

    public function update(UpdateBasePricesRequest $request, UpdateBasePrices $action): AnonymousResourceCollection
    {
        $action->handle($request->user(), $request->validated('prices'));

        return $this->index();
    }
}