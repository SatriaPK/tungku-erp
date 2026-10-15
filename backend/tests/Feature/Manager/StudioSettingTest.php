<?php

use App\Models\StudioSetting;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->manager = User::factory()->manager()->create();
    $this->operator = User::factory()->operator()->create();
    $this->client = User::factory()->create();
});

// ─── Index (GET all settings) ────────────────────────────────────────────────

test('manager can get all studio settings', function () {
    actingAs($this->manager);

    $response = getJson('/api/v1/manager/settings');

    $response->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['key', 'value', 'type', 'description', 'updated_at'],
            ],
        ])
        ->assertJsonFragment(['key' => 'po_percentage'])
        ->assertJsonFragment(['key' => 'studio_profit_percentage'])
        ->assertJsonFragment(['key' => 'hold_duration_minutes'])
        ->assertJsonFragment(['key' => 'operating_hours']);
});

test('operator cannot get studio settings', function () {
    actingAs($this->operator);

    $response = getJson('/api/v1/manager/settings');

    $response->assertForbidden();
});

test('client cannot get studio settings', function () {
    actingAs($this->client);

    $response = getJson('/api/v1/manager/settings');

    $response->assertForbidden();
});

test('unauthenticated user cannot get studio settings', function () {
    $response = getJson('/api/v1/manager/settings');

    $response->assertUnauthorized();
});

// ─── Update po_percentage ────────────────────────────────────────────────────

test('manager can update po_percentage with valid value', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 35,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'key' => 'po_percentage',
                'value' => '35',
                'type' => 'decimal',
            ],
        ]);

    expect(StudioSetting::getSetting('po_percentage'))->toBe(35.0);
});

test('manager can set po_percentage to boundary value 30', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 30,
    ]);

    $response->assertOk();
    expect(StudioSetting::getSetting('po_percentage'))->toBe(30.0);
});

test('manager can set po_percentage to boundary value 70', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 70,
    ]);

    $response->assertOk();
    expect(StudioSetting::getSetting('po_percentage'))->toBe(70.0);
});

test('manager cannot set po_percentage below 30', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 20,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('manager cannot set po_percentage above 70', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 80,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('operator cannot update po_percentage', function () {
    actingAs($this->operator);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 35,
    ]);

    $response->assertForbidden();
});

test('client cannot update po_percentage', function () {
    actingAs($this->client);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 35,
    ]);

    $response->assertForbidden();
});

// ─── Update hold_duration_minutes ────────────────────────────────────────────

test('manager can update hold_duration_minutes with valid value', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/hold_duration_minutes', [
        'value' => 15,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'key' => 'hold_duration_minutes',
                'value' => '15',
                'type' => 'integer',
            ],
        ]);

    expect(StudioSetting::getSetting('hold_duration_minutes'))->toBe(15);
});

test('manager can set hold_duration_minutes to boundary value 10', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/hold_duration_minutes', [
        'value' => 10,
    ]);

    $response->assertOk();
    expect(StudioSetting::getSetting('hold_duration_minutes'))->toBe(10);
});

test('manager can set hold_duration_minutes to boundary value 30', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/hold_duration_minutes', [
        'value' => 30,
    ]);

    $response->assertOk();
    expect(StudioSetting::getSetting('hold_duration_minutes'))->toBe(30);
});

test('manager cannot set hold_duration_minutes below 10', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/hold_duration_minutes', [
        'value' => 5,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('manager cannot set hold_duration_minutes above 30', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/hold_duration_minutes', [
        'value' => 40,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

// ─── Update operating_hours ──────────────────────────────────────────────────

test('manager can update operating_hours with valid JSON', function () {
    actingAs($this->manager);

    $validHours = [
        'monday' => '09:00-17:00',
        'tuesday' => '09:00-17:00',
        'wednesday' => '09:00-17:00',
        'thursday' => '09:00-17:00',
        'friday' => '09:00-17:00',
        'saturday' => '10:00-14:00',
        'sunday' => '00:00-00:00',
    ];

    $response = putJson('/api/v1/manager/settings/operating_hours', [
        'value' => json_encode($validHours),
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'key' => 'operating_hours',
                'type' => 'json',
            ],
        ]);

    expect(StudioSetting::getSetting('operating_hours'))->toBe($validHours);
});

test('manager cannot update operating_hours with missing day', function () {
    actingAs($this->manager);

    $invalidHours = [
        'monday' => '09:00-17:00',
        'tuesday' => '09:00-17:00',
        // missing wednesday
        'thursday' => '09:00-17:00',
        'friday' => '09:00-17:00',
        'saturday' => '10:00-14:00',
        'sunday' => '00:00-00:00',
    ];

    $response = putJson('/api/v1/manager/settings/operating_hours', [
        'value' => json_encode($invalidHours),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('manager cannot update operating_hours with invalid time format', function () {
    actingAs($this->manager);

    $invalidHours = [
        'monday' => '9:00-17:00', // Should be 09:00
        'tuesday' => '09:00-17:00',
        'wednesday' => '09:00-17:00',
        'thursday' => '09:00-17:00',
        'friday' => '09:00-17:00',
        'saturday' => '10:00-14:00',
        'sunday' => '00:00-00:00',
    ];

    $response = putJson('/api/v1/manager/settings/operating_hours', [
        'value' => json_encode($invalidHours),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('manager cannot update operating_hours with invalid JSON', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/operating_hours', [
        'value' => 'not-valid-json',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

// ─── Updated By Tracking ─────────────────────────────────────────────────────

test('updated_by is set when manager updates setting', function () {
    actingAs($this->manager);

    putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 40,
    ]);

    $setting = StudioSetting::where('key', 'po_percentage')->first();

    expect($setting->updated_by)->toBe($this->manager->id);
});

test('updated_by relation is loaded in response', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', [
        'value' => 45,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'updated_by' => ['id', 'name', 'email'],
            ],
        ]);
});

// ─── Missing Value ───────────────────────────────────────────────────────────

test('manager cannot update setting without value', function () {
    actingAs($this->manager);

    $response = putJson('/api/v1/manager/settings/po_percentage', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});
