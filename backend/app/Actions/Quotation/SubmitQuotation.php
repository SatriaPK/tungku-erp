<?php

namespace App\Actions\Quotation;

use App\Actions\Pricing\CalculatePackagePrice;
use App\Enum\OfferSide;
use App\Enum\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

            $quotation->offers()->create([
                'offered_by' => $client->id,
                'side' => OfferSide::Client,
                'price' => $data['offer_price'],
                'note' => $data['notes'] ?? null,
            ]);

            return $quotation->load('latestOffer');
        });
    }
}
