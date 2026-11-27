<?php

namespace App\Actions\Quotation;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Actions\Notification\NotifyBusinessEvent;
use App\Actions\Pricing\CalculatePackagePrice;
use App\Enum\OfferSide;
use App\Enum\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitQuotation
{
    public function __construct(
        protected CalculatePackagePrice $priceCalculator
    ) {}

    /**
     * Client submits a new custom package quotation.
     */
    public function handle(User $client, array $data): Quotation
    {
        if (! $client->isClient() || ! $client->is_active) {
            throw ValidationException::withMessages(['client' => 'Quotations require active clients.']);
        }
        $name = $data['name'] ?? null;
        $data['name'] = is_string($name) ? Str::trim($name) : $name;
        Validator::make(['name' => $data['name']], ['name' => ['required', 'string', 'max:100']])->validate();

        return DB::transaction(function () use ($client, $data) {
            // Calculate system base price
            $autoPrice = $this->priceCalculator->handleFromInput($data);

            $quotation = Quotation::create([
                'name' => $data['name'],
                'client_id' => $client->id,
                'track_count' => $data['track_count'],
                'recording_hours' => $data['recording_hours'],
                'include_recording' => $data['include_recording'],
                'include_editing' => $data['include_editing'],
                'include_mixing' => $data['include_mixing'],
                'include_mastering' => $data['include_mastering'],
                'notes' => $data['notes'] ?? null,
                'status' => QuotationStatus::AwaitingStudio, // Waiting for Studio Manager turn
                'auto_price' => $autoPrice,
            ]);

            $offer = $quotation->offers()->create([
                'offered_by' => $client->id,
                'side' => OfferSide::Client,
                'price' => $data['offer_price'],
                'note' => $data['notes'] ?? null,
            ]);
            $audit = app(RecordBusinessChange::class);
            $audit->handle($quotation, 'quotation.created', [], AuditActor::user($client));
            $audit->handle($offer, 'quotation.offer_created', [], AuditActor::user($client));

            app(NotifyBusinessEvent::class)->quotation($quotation, 'quotation_submitted');

            return $quotation->load('latestOffer');
        });
    }
}
