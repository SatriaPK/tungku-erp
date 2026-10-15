<?php

use App\Models\StudioSetting;
use App\Models\User;
use Database\Seeders\StudioSettingSeeder;

beforeEach(function () {
    $this->seed(StudioSettingSeeder::class);
    $this->manager = User::factory()->manager()->create();
});

test('getSetting returns correct type for po_percentage', function () {
    $value = StudioSetting::getSetting('po_percentage');

    expect($value)->toBeFloat()
        ->and($value)->toBe(50.0);
});

test('getSetting returns correct type for hold_duration_minutes', function () {
    $value = StudioSetting::getSetting('hold_duration_minutes');

    expect($value)->toBeInt()
        ->and($value)->toBe(15);
});

test('getSetting returns array for operating_hours', function () {
    $value = StudioSetting::getSetting('operating_hours');

    expect($value)->toBeArray()
        ->and($value)->toHaveKeys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
        ->and($value['monday'])->toBe('09:00-22:00');
});

test('getSetting throws exception for non-existent key', function () {
    StudioSetting::getSetting('non_existent_key');
})->throws(InvalidArgumentException::class, "Setting 'non_existent_key' not found.");

test('updateSetting updates value and updated_by', function () {
    $setting = StudioSetting::updateSetting('po_percentage', 35, $this->manager->id);

    expect($setting->value)->toBe('35')
        ->and($setting->updated_by)->toBe($this->manager->id);

    $value = StudioSetting::getSetting('po_percentage');
    expect($value)->toBe(35.0);
});

test('updateSetting rejects po_percentage below 30', function () {
    StudioSetting::updateSetting('po_percentage', 20, $this->manager->id);
})->throws(InvalidArgumentException::class, 'PO percentage must be between 30 and 70, got 20.');

test('updateSetting rejects po_percentage above 70', function () {
    StudioSetting::updateSetting('po_percentage', 80, $this->manager->id);
})->throws(InvalidArgumentException::class, 'PO percentage must be between 30 and 70, got 80.');

test('updateSetting accepts po_percentage at boundary 30', function () {
    $setting = StudioSetting::updateSetting('po_percentage', 30, $this->manager->id);

    expect(StudioSetting::getSetting('po_percentage'))->toBe(30.0);
});

test('updateSetting accepts po_percentage at boundary 70', function () {
    $setting = StudioSetting::updateSetting('po_percentage', 70, $this->manager->id);

    expect(StudioSetting::getSetting('po_percentage'))->toBe(70.0);
});

test('updateSetting rejects hold_duration_minutes below 10', function () {
    StudioSetting::updateSetting('hold_duration_minutes', 5, $this->manager->id);
})->throws(InvalidArgumentException::class, 'Hold duration must be between 10 and 30, got 5.');

test('updateSetting rejects hold_duration_minutes above 30', function () {
    StudioSetting::updateSetting('hold_duration_minutes', 35, $this->manager->id);
})->throws(InvalidArgumentException::class, 'Hold duration must be between 10 and 30, got 35.');

test('updateSetting accepts hold_duration_minutes at boundaries', function () {
    StudioSetting::updateSetting('hold_duration_minutes', 10, $this->manager->id);
    expect(StudioSetting::getSetting('hold_duration_minutes'))->toBe(10);

    StudioSetting::updateSetting('hold_duration_minutes', 30, $this->manager->id);
    expect(StudioSetting::getSetting('hold_duration_minutes'))->toBe(30);
});

test('updateSetting accepts valid operating_hours JSON', function () {
    $newHours = [
        'monday' => '08:00-20:00',
        'tuesday' => '08:00-20:00',
        'wednesday' => '08:00-20:00',
        'thursday' => '08:00-20:00',
        'friday' => '08:00-20:00',
        'saturday' => '10:00-18:00',
        'sunday' => '10:00-18:00',
    ];

    StudioSetting::updateSetting('operating_hours', $newHours, $this->manager->id);

    $value = StudioSetting::getSetting('operating_hours');
    expect($value['monday'])->toBe('08:00-20:00')
        ->and($value['saturday'])->toBe('10:00-18:00');
});

test('updateSetting rejects operating_hours with missing days', function () {
    $invalidHours = [
        'monday' => '09:00-22:00',
        'tuesday' => '09:00-22:00',
        // missing other days
    ];

    StudioSetting::updateSetting('operating_hours', $invalidHours, $this->manager->id);
})->throws(InvalidArgumentException::class, 'Operating hours must include all 7 days');

test('updateSetting rejects operating_hours with invalid format', function () {
    $invalidHours = [
        'monday' => '9:00-22:00', // missing leading zero
        'tuesday' => '09:00-22:00',
        'wednesday' => '09:00-22:00',
        'thursday' => '09:00-22:00',
        'friday' => '09:00-22:00',
        'saturday' => '09:00-22:00',
        'sunday' => '09:00-22:00',
    ];

    StudioSetting::updateSetting('operating_hours', $invalidHours, $this->manager->id);
})->throws(InvalidArgumentException::class, 'Invalid format for monday: expected HH:MM-HH:MM');

test('updatedBy relation works correctly', function () {
    StudioSetting::updateSetting('po_percentage', 40, $this->manager->id);

    $setting = StudioSetting::where('key', 'po_percentage')->first();

    expect($setting->updatedBy)->not->toBeNull()
        ->and($setting->updatedBy->id)->toBe($this->manager->id)
        ->and($setting->updatedBy->name)->toBe($this->manager->name);
});
