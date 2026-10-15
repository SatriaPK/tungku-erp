<?php

use App\Actions\Project\GetManagerDashboardProgress;
use App\Actions\Quotation\SubmitQuotation;
use App\Enum\QuotationStatus;
use App\Enum\StageType;
use App\Models\BasePrice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\StudioSettingSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function namedQuotationPayload(): array
{
    return ['name' => '  Album Senja 日本  ', 'track_count' => 1, 'recording_hours' => 0,
        'include_recording' => false, 'include_editing' => true, 'include_mixing' => false,
        'include_mastering' => false, 'offer_price' => 100000];
}

it('preserves the client name through API dashboard and accepted quotation checkout', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00', 'Asia/Jakarta'));
    $this->seed(StudioSettingSeeder::class);
    BasePrice::create(['stage' => StageType::Editing, 'price' => 100000, 'unit' => 'track']);
    $client = User::factory()->client()->create();
    $foreign = User::factory()->client()->create();
    $response = $this->actingAs($client)->postJson('/api/v1/client/quotations', [...namedQuotationPayload(), 'client_id' => $foreign->id]);
    $response->assertCreated()->assertJsonPath('data.name', 'Album Senja 日本')
        ->assertJsonPath('data.package_display_name', 'Album Senja 日本')->assertJsonPath('data.client_id', $client->id);
    $quotation = Quotation::findOrFail($response->json('data.id'));
    expect($quotation->name)->toBe('Album Senja 日本')
        ->and(app(GetManagerDashboardProgress::class)->handle()['quotation_progress'][0]['name'])->toBe('Album Senja 日本');
    $this->actingAs($foreign)->getJson('/api/v1/client/quotations/'.$quotation->id)->assertForbidden();
    $this->actingAs(User::factory()->manager()->create())->getJson('/api/v1/manager/quotations/'.$quotation->id)
        ->assertOk()->assertJsonPath('data.package_display_name', 'Album Senja 日本');
    $quotation->update(['status' => QuotationStatus::Accepted, 'agreed_price' => 100000]);
    $this->actingAs($client)->postJson('/api/v1/client/bookings/hold', [
        'quotation_id' => $quotation->id, 'room_id' => Room::factory()->create()->id,
        'start_at' => '2026-10-16 10:00:00', 'end_at' => '2026-10-16 12:00:00',
    ])->assertCreated();
    $project = Project::where('quotation_id', $quotation->id)->firstOrFail();
    expect($project->name)->toBe('Album Senja 日本')->and($project->package_snapshot['name'])->toBe('Album Senja 日本');
});

it('rejects invalid new names through HTTP and the Action without creating records', function (mixed $name) {
    $client = User::factory()->client()->create();
    $data = namedQuotationPayload();
    unset($data['name']);
    if ($name !== null) {
        $data['name'] = $name;
    }
    $this->actingAs($client)->postJson('/api/v1/client/quotations', $data)->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(fn () => app(SubmitQuotation::class)->handle($client, $data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('quotations', 0);
    $this->assertDatabaseCount('quotation_offers', 0);
})->with(['missing' => [null], 'blank' => ['   '], 'unicode whitespace' => ["\u{00A0}"], 'too long' => [str_repeat('界', 101)], 'nonstring' => [123]]);

it('trims and accepts a 100 character name through the Action as plain text', function () {
    BasePrice::create(['stage' => StageType::Editing, 'price' => 100000, 'unit' => 'track']);
    $name = '<b>'.str_repeat('界', 93).'</b>';
    $quotation = app(SubmitQuotation::class)->handle(User::factory()->client()->create(), [...namedQuotationPayload(), 'name' => ' '.$name.' ']);
    expect($quotation->name)->toBe($name)->and(mb_strlen($quotation->name))->toBe(100);
});

it('keeps legacy names nullable across isolated migration down and up', function () {
    $client = User::factory()->client()->create();
    $quotation = Quotation::create(['client_id' => $client->id, 'track_count' => 1, 'recording_hours' => 0, 'auto_price' => 100]);
    $migration = require database_path('migrations/2026_10_15_135750_add_name_to_quotations_table.php');
    $migration->down();
    expect(Schema::hasColumn('quotations', 'name'))->toBeFalse();
    $migration->up();
    expect(Schema::hasColumn('quotations', 'name'))->toBeTrue()
        ->and($quotation->refresh()->name)->toBeNull()
        ->and($quotation->packageDisplayName())->toBe('Penawaran Kustom #'.$quotation->id);
    $this->actingAs($client)->getJson('/api/v1/client/quotations/'.$quotation->id)->assertOk()
        ->assertJsonPath('data.name', null)->assertJsonPath('data.package_display_name', 'Penawaran Kustom #'.$quotation->id);
});
