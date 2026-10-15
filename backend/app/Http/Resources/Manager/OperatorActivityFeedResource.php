<?php

namespace App\Http\Resources\Manager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorActivityFeedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['as_of' => $this['as_of'], 'timezone' => $this['timezone'], 'items' => $this['items']];
    }
}
