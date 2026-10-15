<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Booking\CancelSlot;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecordingSessionListResource;
use App\Http\Resources\RecordingSessionResource;
use App\Models\RecordingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class BookingController extends Controller
{
    /**
     * List all bookings (manager view).
     */
    public function index(): AnonymousResourceCollection
    {
        $sessions = RecordingSession::with(['room', 'client'])
            ->latest('start_at')
            ->paginate(15);

        return RecordingSessionListResource::collection($sessions);
    }

    /**
     * View a single booking detail.
     */
    public function show(int $id): JsonResponse
    {
        $session = RecordingSession::with(['room', 'client'])
            ->findOrFail($id);

        $this->authorize('view', $session);

        return (new RecordingSessionResource($session))->response();
    }

    /**
     * Cancel any booking (manager privilege).
     */
    public function destroy(int $id): JsonResponse
    {
        $session = RecordingSession::findOrFail($id);

        $this->authorize('cancel', $session);

        try {
            $session = app(CancelSlot::class)->handle($session->id, 'Cancelled by manager');
            $session->load(['room', 'client']);

            return (new RecordingSessionResource($session))->response();
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The booking cannot be cancelled.',
                'errors' => [
                    'id' => [$exception->getMessage()],
                ],
            ], 422);
        }
    }
}
