<?php

namespace App\Http\Resources;

use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordingSession
 */
class RecordingSessionListResource extends JsonResource
{
    /**
     * Lightweight resource for list views.
     * Uses whenLoaded() to prevent N+1 queries; callers must eager-load
     * 'room' and 'client' before wrapping in a collection.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_name' => $this->whenLoaded('room', function () {
                /** @var Room $room */
                $room = $this->room;

                return $room->name;
            }),
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', function () {
                /** @var User $client */
                $client = $this->client;

                return $client->name;
            }),
            'start_at' => $this->start_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'end_at' => $this->end_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
