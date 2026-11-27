<?php

namespace App\Actions\CRM;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Enum\CampaignStatus;
use App\Enum\CRMChannel;
use App\Enum\Role;
use App\Models\CRMCampaign;
use App\Models\CRMCampaignRecipient;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCampaign
{
    private const MAX_RECIPIENTS = 500;

    /**
     * @param  array{ name: string, message: string, channels: array<int, string>, client_ids: array<int, int>, package_id?: int|null }  $data
     */
    public function handle(User $manager, array $data): CRMCampaign
    {
        if (! $manager->is_active || $manager->role !== Role::Manager) {
            throw ValidationException::withMessages([
                'manager' => 'Hanya Manager aktif yang dapat membuat kampanye CRM.',
            ]);
        }

        $channels = collect($data['channels'])->map(
            fn (string $channel): CRMChannel => CRMChannel::from($channel),
        );

        if ($channels->isEmpty() || $channels->unique()->count() !== $channels->count()) {
            throw ValidationException::withMessages([
                'channels' => 'Kanal wajib diisi dan tidak boleh duplikat.',
            ]);
        }

        if ($channels->contains(fn (CRMChannel $channel): bool => $channel !== CRMChannel::Email)) {
            throw ValidationException::withMessages([
                'channels' => 'Kampanye CRM saat ini hanya mendukung email.',
            ]);
        }

        $clientIds = collect($data['client_ids'])->unique()->values();

        if ($clientIds->isEmpty() || $clientIds->count() > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([
                'client_ids' => 'Kampanye harus memiliki 1–500 klien unik.',
            ]);
        }

        return DB::transaction(function () use ($manager, $data, $channels, $clientIds): CRMCampaign {
            if (isset($data['package_id']) && ! Package::query()->whereKey($data['package_id'])->exists()) {
                throw ValidationException::withMessages([
                    'package_id' => 'Paket tidak ditemukan.',
                ]);
            }

            $clients = User::query()
                ->whereIn('id', $clientIds)
                ->where('role', Role::Client->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($clients->count() !== $clientIds->count()) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Sebagian penerima bukan akun klien yang valid.',
                ]);
            }

            $campaign = CRMCampaign::query()->create([
                'created_by' => $manager->id,
                'name' => $data['name'],
                'package_id' => $data['package_id'] ?? null,
                'message' => $data['message'],
                'channels' => $channels,
                'status' => CampaignStatus::Draft,
            ]);

            $recipients = $clients->flatMap(fn (User $client): Collection => $channels->map(
                fn (CRMChannel $channel): array => [
                    'crm_campaign_id' => $campaign->id,
                    'client_id' => $client->id,
                    'channel' => $channel->value,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ));

            CRMCampaignRecipient::query()->insert($recipients->all());
            app(RecordBusinessChange::class)->handle($campaign, 'campaign.created', [], AuditActor::user($manager));

            return $campaign->load('recipients');
        });
    }
}
