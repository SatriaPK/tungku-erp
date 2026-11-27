<?php

namespace App\Actions\Audit;

use App\Models\BasePrice;
use App\Models\CRMCampaign;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\RecordingSession;
use App\Models\StudioSetting;
use App\Models\TrackStageProgress;
use App\Models\TrackStageSubmission;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Activity;

class RecordBusinessChange
{
    /** @return array<string, mixed> */
    public function snapshot(Model $subject): array
    {
        if (! $subject->exists) {
            return [];
        }
        $keys = match ($subject::class) {
            RecordingSession::class => ['room_id', 'client_id', 'project_id', 'project_stage_id', 'payment_id', 'status', 'start_at', 'end_at', 'held_until', 'is_extension', 'extra_fee'],
            Project::class => ['client_id', 'package_id', 'quotation_id', 'status', 'price', 'po_amount', 'final_payment_amount', 'completed_at'],
            ProjectStage::class => ['project_id', 'stage', 'status', 'operator_id', 'deadline_at', 'assigned_at', 'started_at', 'completed_at'],
            TrackStageSubmission::class => ['track_stage_progress_id', 'version', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_decision'],
            TrackStageProgress::class => ['project_stage_id', 'project_track_id', 'status', 'current_version', 'revision_count', 'approved_at', 'approved_by'],
            Quotation::class => ['client_id', 'status', 'auto_price', 'agreed_price', 'closed_by', 'closed_at'],
            QuotationOffer::class => ['quotation_id', 'offered_by', 'side', 'price'],
            Payment::class => ['project_id', 'invoice_id', 'type', 'status', 'provider', 'amount', 'order_id', 'expires_at', 'paid_at'],
            Invoice::class => ['client_id', 'project_id', 'recording_session_id', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'total', 'paid_at'],
            Package::class => ['auto_price', 'manual_price', 'is_price_manual', 'track_count', 'recording_hours', 'include_recording', 'include_editing', 'include_mixing', 'include_mastering', 'is_active', 'deleted_at'],
            BasePrice::class => ['stage', 'price', 'unit'],
            Expense::class => ['expense_category_id', 'amount', 'spent_on'],
            ExpenseCategory::class, InventoryCategory::class => ['name', 'is_active'],
            Payroll::class => ['period_year', 'period_month', 'status', 'studio_profit_percentage', 'studio_profit_amount', 'total_revenue', 'total_expense', 'net_profit', 'finalized_at', 'finalized_by'],
            Inventory::class => ['room_id', 'inventory_category_id', 'status', 'purchase_date', 'purchase_price', 'residual_value', 'useful_life_months', 'disposed_on', 'disposal_value', 'expense_id', 'deleted_at'],
            CRMCampaign::class => ['status', 'channels', 'package_id'],
            User::class => ['crm_consent_at'],
            StudioSetting::class => in_array($subject->getAttribute('key'), ['po_percentage', 'studio_profit_percentage', 'hold_duration_minutes', 'operating_hours'], true) ? ['key', 'value'] : ['key'],
            default => throw new LogicException('Missing business audit allowlist for '.$subject::class),
        };
        $values = $subject->only($keys);
        if ($subject instanceof Payroll) {
            $values['allocations'] = $subject->items()->orderBy('operator_id')->get(['operator_id', 'percentage', 'amount'])->toArray();
        }
        if ($subject instanceof CRMCampaign) {
            $values['recipient_count'] = $subject->recipients()->count();
        }

        return $this->normalize($values);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function handle(Model $subject, string $event, array $before, AuditActor $actor, ?string $eventKey = null, bool $deleted = false): void
    {
        $connection = $subject->getConnection();
        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Business audit must be recorded inside the domain transaction.');
        }
        $after = $deleted ? [] : $this->snapshot($subject);
        if ($before === $after) {
            return;
        }
        $key = $eventKey ?? (string) Str::uuid();
        Activity::on($connection->getName())->firstOrCreate(['event_key' => $key], [
            'log_name' => 'business', 'description' => $event, 'event' => $event,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'causer_type' => $actor->userId === null ? null : User::class, 'causer_id' => $actor->userId,
            'properties' => ['schema_version' => 1, 'event_key' => $key, 'event' => $event,
                'occurred_at' => now()->toIso8601String(), 'actor_kind' => $actor->kind,
                'actor_id' => $actor->userId, 'actor_source' => $actor->source,
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                'old' => $before, 'attributes' => $after],
        ]);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->setTimezone(config('app.timezone'))->toIso8601String(),
                is_array($value) => $this->normalize($value),
                default => $value,
            };
        }

        return $values;
    }
}
