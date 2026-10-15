<?php

namespace App\Http\Resources;

use App\Models\StudioSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudioSetting
 */
class StudioSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'description' => $this->description,
            'updated_by' => $this->whenLoaded('updatedBy', fn () => new UserResource($this->updatedBy)),
            'updated_at' => $this->updated_at,
        ];
    }
}
