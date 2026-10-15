<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateStudioSettingRequest;
use App\Http\Resources\StudioSettingResource;
use App\Models\StudioSetting;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudioSettingController extends Controller
{
    /**
     * Get all studio settings.
     */
    public function index(): AnonymousResourceCollection
    {
        $settings = StudioSetting::with('updatedBy')->get();

        return StudioSettingResource::collection($settings);
    }

    /**
     * Update a specific studio setting.
     */
    public function update(UpdateStudioSettingRequest $request, string $key): StudioSettingResource
    {
        $value = $request->input('value');

        // For operating_hours, decode JSON if it's a string
        if ($key === 'operating_hours' && is_string($value)) {
            $value = json_decode($value, true);
        }

        $setting = StudioSetting::updateSetting($key, $value, $request->user()->id);

        return new StudioSettingResource($setting->load('updatedBy'));
    }
}
