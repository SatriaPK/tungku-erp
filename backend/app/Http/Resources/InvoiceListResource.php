<?php

namespace App\Http\Resources;

use App\Enum\InvoiceStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_status' => $this->billingStatus()[0],
            'billing_status_label' => $this->billingStatus()[1],
            'total' => $this->total,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at->copy()->setTimezone(config('app.timezone'))->toIso8601String(),

            // Minimal relations
            'project' => $this->whenLoaded('project', function () {
                /** @var Project|null $project */
                $project = $this->project;

                return $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                ] : null;
            }),
        ];
    }

    /**
     * Status tagihan dari sudut pandang manajer: membedakan DP (invoice PO)
     * dan pelunasan (invoice dengan metadata payment_type = final).
     *
     * @return array{0: string, 1: string}
     */
    private function billingStatus(): array
    {
        $isFinal = ($this->metadata['payment_type'] ?? null) === PaymentType::Final->value;

        return match ($this->status) {
            InvoiceStatus::Draft => ['draft', 'Draft'],
            InvoiceStatus::Cancelled => ['cancelled', 'Dibatalkan'],
            InvoiceStatus::Refunded => ['refunded', 'Dikembalikan'],
            InvoiceStatus::Issued => $isFinal
                ? ['awaiting_final_payment', 'Menunggu pelunasan']
                : ['awaiting_payment', 'Menunggu pembayaran'],
            InvoiceStatus::Paid => $isFinal || ! $this->relationLoaded('project') || $this->project === null || $this->project->status === ProjectStatus::Completed
                ? ['paid', 'Lunas']
                : ['down_payment_paid', 'DP dibayar'],
        };
    }
}
