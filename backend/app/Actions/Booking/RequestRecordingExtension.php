<?php

namespace App\Actions\Booking;

use App\Enum\PriceUnit;
use App\Enum\ProjectStatus;
use App\Enum\Role;
use App\Enum\SessionStatus;
use App\Enum\StageStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestRecordingExtension
{
    public function __construct(private readonly CheckAvailability $checkAvailability) {}

    public function handle(ProjectStage $stage, User $operator, int $roomId, CarbonImmutable $startAt, CarbonImmutable $endAt): RecordingSession
    {
        return DB::transaction(function () use ($stage, $operator, $roomId, $startAt, $endAt): RecordingSession {
            $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();
            $stage = ProjectStage::query()->lockForUpdate()->findOrFail($stage->id);
            $project = Project::query()->lockForUpdate()->findOrFail($stage->project_id);
            $operator = User::query()->findOrFail($operator->id);

            if ($operator->role !== Role::Operator || ! $operator->is_active || (int) $stage->operator_id !== (int) $operator->id) {
                throw ValidationException::withMessages([
                    'operator' => 'Hanya Operator aktif yang ditugaskan dapat meminta extension Recording.',
                ]);
            }

            if ($project->status !== ProjectStatus::InProgress
                || $stage->stage !== StageType::Recording
                || $stage->status !== StageStatus::InProgress
                || ($stage->deadline_at !== null && CarbonImmutable::now()->greaterThanOrEqualTo($stage->deadline_at))
                || ($stage->deadline_at !== null && $endAt->greaterThan($stage->deadline_at))) {
                throw ValidationException::withMessages([
                    'stage' => 'Extension hanya dapat diminta pada tahap Recording aktif di Project yang berjalan.',
                ]);
            }

            if ($endAt->lessThanOrEqualTo($startAt)) {
                throw ValidationException::withMessages([
                    'end_at' => 'Waktu akhir harus setelah waktu mulai.',
                ]);
            }

            if ($startAt->lessThanOrEqualTo(CarbonImmutable::now(config('app.timezone')))) {
                throw ValidationException::withMessages([
                    'start_at' => 'Slot extension harus dimulai pada waktu mendatang.',
                ]);
            }

            $durationMinutes = (int) ceil(($endAt->getTimestamp() - $startAt->getTimestamp()) / 60);

            $price = BasePrice::query()->where('stage', StageType::Recording->value)->lockForUpdate()->first();

            if ($price === null || $price->unit !== PriceUnit::Hour) {
                throw ValidationException::withMessages([
                    'price' => 'Tarif Recording per jam belum tersedia atau tidak valid.',
                ]);
            }

            if ($room === null || ! $room->is_active) {
                throw ValidationException::withMessages([
                    'room_id' => 'Ruang Recording tidak tersedia.',
                ]);
            }

            $availability = $this->checkAvailability->handle($roomId, $startAt->toMutable(), $endAt->toMutable());

            if (! $availability['available']) {
                $message = $availability['reason'] ?? 'Slot Recording tidak tersedia.';

                if (str_contains($message, 'conflicts')) {
                    throw ValidationException::withMessages(['slot' => 'Slot Recording bertabrakan dengan sesi atau hold aktif.']);
                }

                throw ValidationException::withMessages(['slot' => $message]);
            }

            $billableHours = intdiv($durationMinutes + 59, 60);

            return RecordingSession::query()->create([
                'room_id' => $roomId,
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'project_stage_id' => $stage->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => SessionStatus::Confirmed,
                'held_until' => null,
                'is_extension' => true,
                'extra_fee' => $billableHours * $price->price,
                'requested_by' => $operator->id,
            ]);
        });
    }
}
