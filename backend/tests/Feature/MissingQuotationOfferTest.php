<?php
use App\Models\{User,Quotation};
use App\Enum\QuotationStatus;

test('manager supplies missing studio price and client can then accept it', function () {
 $client=User::factory()->client()->create(); $manager=User::factory()->manager()->create();
 $quote=Quotation::create(['client_id'=>$client->id,'name'=>'Produksi mini album','track_count'=>1,'recording_hours'=>3,'include_recording'=>true,'include_editing'=>true,'include_mixing'=>false,'include_mastering'=>false,'auto_price'=>1500000,'status'=>QuotationStatus::AwaitingClient]);
 $url="/api/v1/manager/quotations/{$quote->id}/send-missing-offer";
 $this->actingAs($client)->postJson($url,['price'=>1200000])->assertForbidden();
 $this->actingAs($manager)->postJson($url,['price'=>0])->assertUnprocessable();
 $result=$this->postJson($url,['price'=>1200000])->assertOk()->assertJsonPath('data.latest_offer.side','studio')->assertJsonPath('data.latest_offer.price',1200000);
 $this->postJson($url,['price'=>1300000])->assertUnprocessable();
 $this->actingAs($client)->postJson("/api/v1/client/quotations/{$quote->id}/respond",['response_type'=>'accept','latest_offer_id'=>$result->json('data.latest_offer.id')])->assertOk()->assertJsonPath('data.status','accepted')->assertJsonPath('data.agreed_price',1200000);
});
