<?php

namespace App\Actions\Quotation;

use App\Enum\OfferSide;
use App\Enum\QuotationResponseType;
use App\Enum\QuotationStatus;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RespondToQuotation
{
    /**
     * @throws RuntimeException Bila bukan gilirannya, offer ID usang, atau status ditutup.
     */
    public function handle(Quotation $quotation, User $user, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $user, $data) {
            // Lock for validation to prevent race conditions
            $quotation = Quotation::where('id', $quotation->id)->lockForUpdate()->firstOrFail();

            if (! $quotation->status->isOpen()) {
                throw new RuntimeException('Penawaran sudah ditutup.');
            }

            $userSide = $user->isClient() ? OfferSide::Client : OfferSide::Studio;

            if (($userSide === OfferSide::Studio && ! $user->isManager()) || ($userSide === OfferSide::Client && ! $quotation->client()->whereKey($user->id)->exists())) {
                throw new RuntimeException('Anda tidak berwenang merespons penawaran ini.');
            }

            if (! $quotation->isTurnOf($userSide)) {
                throw new RuntimeException('Sekarang bukan giliran Anda untuk merespon.');
            }

            /** @var QuotationOffer|null $latestOffer */
            $latestOffer = $quotation->latestOffer()->first();

            // Check stale offer
            if (! $latestOffer || (int) $data['latest_offer_id'] !== $latestOffer->id) {
                throw new RuntimeException('Penawaran yang Anda respon sudah usang, silakan muat ulang halaman.');
            }

            $responseType = QuotationResponseType::from($data['response_type']);

            if ($responseType === QuotationResponseType::Decline) {
                $quotation->update([
                    'status' => QuotationStatus::Declined,
                    'closed_by' => $user->id,
                    'closed_at' => now(),
                ]);
            } elseif ($responseType === QuotationResponseType::Accept) {
                // Determine agreed price from the last opposite offer
                $quotation->update([
                    'status' => QuotationStatus::Accepted,
                    'agreed_price' => $latestOffer->price,
                    'closed_by' => $user->id,
                    'closed_at' => now(),
                ]);
            } elseif ($responseType === QuotationResponseType::Counter) {
                if (! isset($data['price']) || (int) $data['price'] < 0) {
                    throw new InvalidArgumentException('Harga tawar balik diperlukan.');
                }

                $quotation->offers()->create([
                    'offered_by' => $user->id,
                    'side' => $userSide,
                    'price' => $data['price'],
                    'note' => $data['note'] ?? null,
                ]);

                $quotation->update([
                    'status' => $userSide->statusAfterOffer(),
                ]);
            }

            return $quotation->fresh(['latestOffer']);
        });
    }
}
