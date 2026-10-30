<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class BusinessEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, int|string> */
    public readonly array $metadata;

    /** @param array<string, int|string> $metadata */
    public function __construct(public readonly string $eventKey, public readonly string $type, array $metadata)
    {
        $allowed = ['project_id', 'stage_id', 'track_id', 'submission_id', 'version', 'operator_id',
            'quotation_id', 'offer_id', 'session_id', 'code', 'status', 'price', 'start_at', 'end_at',
            'billable_hours', 'hourly_rate', 'extra_fee'];
        if (array_diff(array_keys($metadata), $allowed) !== []) {
            throw new InvalidArgumentException('Unsupported notification metadata.');
        }
        $this->metadata = $metadata;
        $this->title();
        $this->onQueue('notifications');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function title(): string
    {
        return match ($this->type) {
            'stage_assigned' => 'Penugasan tahap produksi',
            'result_submitted' => 'Hasil menunggu review',
            'revision_requested' => 'Revisi hasil diminta',
            'stage_ready' => 'Tahap berikutnya siap dikerjakan',
            'quotation_submitted' => 'Pengajuan penawaran baru',
            'quotation_countered' => 'Penawaran harga terbaru',
            'quotation_accepted' => 'Penawaran diterima',
            'quotation_declined' => 'Penawaran ditolak',
            'project_created' => 'Project dibuat, menunggu pembayaran',
            'project_awaiting_assignment' => 'PO lunas, project siap ditugaskan',
            'recording_extended' => 'Jadwal dan biaya tambahan Recording',
            default => throw new InvalidArgumentException('Unsupported business notification type.'),
        };
    }

    /** @return array{schema_version: int, event_key: string, type: string, title: string, body: string, target: array{type: string, id: int}|null, metadata: array<string, int|string>} */
    public function toArray(object $notifiable): array
    {
        $target = null;
        foreach (['session_id' => 'recording_session', 'quotation_id' => 'quotation', 'stage_id' => 'stage', 'project_id' => 'project'] as $key => $type) {
            if (isset($this->metadata[$key])) {
                $target = ['type' => $type, 'id' => (int) $this->metadata[$key]];
                break;
            }
        }

        return ['schema_version' => 1, 'event_key' => $this->eventKey, 'type' => $this->type,
            'title' => $this->title(), 'body' => $this->type === 'recording_extended'
                ? 'Biaya tambahan Recording Rp '.number_format((int) $this->metadata['extra_fee'], 0, ',', '.').' dimasukkan ke pelunasan project.'
                : $this->title(),
            'target' => $target, 'metadata' => $this->metadata];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->line($this->title());
        if ($this->type === 'recording_extended') {
            return $mail->line('Mulai: '.$this->metadata['start_at'])
                ->line('Selesai: '.$this->metadata['end_at'])
                ->line('Jam ditagihkan: '.$this->metadata['billable_hours'])
                ->line('Tarif per jam: Rp '.number_format((int) $this->metadata['hourly_rate'], 0, ',', '.'))
                ->line('Biaya tambahan: Rp '.number_format((int) $this->metadata['extra_fee'], 0, ',', '.'))
                ->line('Biaya tambahan dimasukkan ke pelunasan project.');
        }

        return $mail->line('Buka aplikasi untuk melihat rincian terbaru.');
    }
}
