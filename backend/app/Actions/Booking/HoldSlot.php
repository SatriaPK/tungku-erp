<?php

namespace App\Actions\Booking;

use App\Actions\Invoice\GenerateInvoice;
use App\Enum\ProjectStatus;
use App\Enum\QuotationStatus;
use App\Enum\SessionStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectTrack;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class HoldSlot
{
    public function __construct(
        private readonly CheckAvailability $checkAvailability,
        private readonly GenerateInvoice $generateInvoice
    ) {}

    /**
     * Hold a recording session slot for a client.
     *
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function handle(
        int $roomId,
        int $clientId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $projectId = null,
        ?int $projectStageId = null,
        ?int $packageId = null,
        ?int $quotationId = null,
        ?string $couponCode = null,
    ): RecordingSession {
        if ($couponCode && $projectId !== null) {
            throw new InvalidArgumentException('Kupon hanya berlaku untuk booking paket atau penawaran baru.');
        }
        if ($packageId !== null && $quotationId !== null) {
            throw new InvalidArgumentException('Specify either a package or a quotation, not both.');
        }

        // Validate room exists
        if (! Room::where('id', $roomId)->exists()) {
            throw new InvalidArgumentException("Room with ID {$roomId} not found.");
        }

        // Validate client exists
        if (! User::where('id', $clientId)->exists()) {
            throw new InvalidArgumentException("Client with ID {$clientId} not found.");
        }

        // Validate time order
        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        // Get hold duration from settings
        $holdDurationMinutes = StudioSetting::getSetting('hold_duration_minutes');
        $heldUntil = now()->addMinutes($holdDurationMinutes);

        return DB::transaction(function () use ($couponCode, $quotationId, $clientId, $roomId, $packageId, $startAt, $endAt, $projectId, $projectStageId, $heldUntil): RecordingSession {
            $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();

            if ($room === null || ! $room->is_active) {
                throw new InvalidArgumentException('Room is not available.');
            }

            $availability = $this->checkAvailability->handle($roomId, $startAt, $endAt);

            if (! $availability['available']) {
                throw new RuntimeException('Slot is not available: '.$availability['reason']);
            }

            $project = null;
            if ($quotationId !== null) {
                $quotation = Quotation::query()->whereKey($quotationId)->lockForUpdate()->first();
                if ($quotation === null
                    || $quotation->client_id !== $clientId
                    || $quotation->status->value !== QuotationStatus::Accepted->value
                    || $quotation->agreed_price === null
                    || $quotation->project()->exists()) {
                    throw new InvalidArgumentException('The quotation is not available for booking.');
                }

                $project = Project::create([
                    'code' => 'QTN-'.strtoupper(bin2hex(random_bytes(6))),
                    'client_id' => $clientId,
                    'quotation_id' => $quotation->id,
                    'name' => $quotation->packageDisplayName(),
                    'package_snapshot' => [
                        'source' => 'quotation', 'quotation_id' => $quotation->id,
                        'name' => $quotation->packageDisplayName(),
                        'track_count' => $quotation->track_count,
                        'recording_hours' => $quotation->recording_hours,
                        'include_recording' => $quotation->include_recording,
                        'include_editing' => $quotation->include_editing,
                        'include_mixing' => $quotation->include_mixing,
                        'include_mastering' => $quotation->include_mastering,
                        'price' => $quotation->agreed_price,
                    ],
                    'price' => $quotation->agreed_price,
                    'po_amount' => $quotation->agreed_price,
                    'status' => ProjectStatus::PendingPayment,
                ]);

                for ($position = 1; $position <= $quotation->track_count; $position++) {
                    ProjectTrack::create(['project_id' => $project->id, 'title' => "Track {$position}", 'position' => $position]);
                }

                foreach ($quotation->includedStages() as $sequence => $stage) {
                    ProjectStage::create(['project_id' => $project->id, 'stage' => $stage, 'sequence' => $sequence + 1]);
                }
                $projectId = $project->id;
            }

            $session = RecordingSession::create([
                'room_id' => $roomId,
                'client_id' => $clientId,
                'package_id' => $packageId,
                'project_id' => $projectId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => SessionStatus::Held,
                'held_until' => $heldUntil,
                'project_stage_id' => $projectStageId,
                'is_extension' => false,
                'extra_fee' => 0,
                'requested_by' => null,
            ]);

            // Generate Invoice
            if ($project !== null) {
                $invoice = $this->generateInvoice->fromProject($project);
            } else {
                $invoice = $this->generateInvoice->fromRecordingSession($session);
            }

            if ($couponCode) app(ApplyBookingCoupon::class)->apply($invoice, $couponCode);

            return $session;
        });
    }
}
