<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Booking\CancelSlot;
use App\Actions\Booking\CheckAvailability;
use App\Actions\Booking\ConfirmSlot;
use App\Actions\Booking\HoldSlot;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\CheckAvailabilityRequest;
use App\Http\Requests\Booking\ConfirmSlotRequest;
use App\Http\Requests\Booking\HoldSlotRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\RecordingSessionListResource;
use App\Http\Resources\RecordingSessionResource;
use App\Models\RecordingSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class BookingController extends Controller
{
    /**
     * List client's own bookings.
     */
    public function index(): AnonymousResourceCollection
    {
        $sessions = RecordingSession::where('client_id', request()->user()->id)
            ->with(['room', 'client'])
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
     * Check if a time slot is available for booking.
     */
    public function checkAvailability(CheckAvailabilityRequest $request): AvailabilityResource
    {
        $validated = $request->validated();

        $result = app(CheckAvailability::class)->handle(
            roomId: $validated['room_id'],
            startAt: Carbon::parse($validated['start_at'], config('app.timezone')),
            endAt: Carbon::parse($validated['end_at'], config('app.timezone'))
        );

        return new AvailabilityResource([
            'available' => $result['available'],
            'reason' => $result['reason'] ?? null,
            'requested_start' => Carbon::parse($validated['start_at'], config('app.timezone'))
                ->setTimezone(config('app.timezone'))
                ->toIso8601String(),
            'requested_end' => Carbon::parse($validated['end_at'], config('app.timezone'))
                ->setTimezone(config('app.timezone'))
                ->toIso8601String(),
            'room_id' => $validated['room_id'],
        ]);
    }

    /**
     * Hold a time slot for checkout (temporary reservation).
     */
    public function holdSlot(HoldSlotRequest $request): JsonResponse
    {
        abort_unless($request->user()->isClient(), 403);
        $validated = $request->validated();

        try {
            $session = app(HoldSlot::class)->handle(
                roomId: $validated['room_id'],
                clientId: $request->user()->id,
                startAt: Carbon::parse($validated['start_at'], config('app.timezone')),
                endAt: Carbon::parse($validated['end_at'], config('app.timezone')),
                projectId: $validated['project_id'] ?? null,
                packageId: $validated['package_id'] ?? null,
                quotationId: $validated['quotation_id'] ?? null,
                couponCode: $validated['coupon_code'] ?? null,
            );

            $session->load(['room', 'client', 'project']);

            return (new RecordingSessionResource($session))
                ->response()
                ->setStatusCode(201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'The selected time slot is unavailable.',
                'errors' => [
                    'start_at' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Confirm a held slot after payment.
     */
    public function confirmSlot(ConfirmSlotRequest $request): JsonResponse
    {
        $session = RecordingSession::findOrFail($request->validated('recording_session_id'));

        $this->authorize('cancel', $session); // same ownership rule: only own session

        if ($session->status->value === 'held' && $session->held_until !== null && $session->held_until->isFuture()
            && $session->payment?->status !== PaymentStatus::Paid) {
            return response()->json(['message' => 'The booking requires a paid payment.',
                'errors' => ['recording_session_id' => ['The booking requires a paid payment.']]], 422);
        }

        try {
            $session = app(ConfirmSlot::class)->handle($session->id);
            $session->load(['room', 'client']);

            return (new RecordingSessionResource($session))->response();
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The recording session cannot be confirmed.',
                'errors' => [
                    'recording_session_id' => [$exception->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Cancel a client's held or confirmed booking.
     */
    public function cancelBooking(CancelBookingRequest $request, int $id): JsonResponse
    {
        $session = RecordingSession::find($id);

        if ($session === null) {
            return response()->json(['message' => 'The booking could not be found.'], 404);
        }

        $this->authorize('cancel', $session);

        try {
            $session = app(CancelSlot::class)->handle($session->id, $request->validated('reason'));
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
