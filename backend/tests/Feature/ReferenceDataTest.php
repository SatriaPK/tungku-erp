<?php

use App\Models\InventoryCategory;
use App\Models\Room;
use App\Models\StudioSetting;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;

test('reference data is complete and repeat seeding preserves manager changes', function () {
    expect(InventoryCategory::count())->toBe(12);
    $room = Room::where('name', 'Studio Utama')->firstOrFail();
    $room->update(['is_active' => false]);
    $category = InventoryCategory::where('name', 'Mikrofon')->firstOrFail();
    $category->update(['is_active' => false]);
    StudioSetting::where('key', 'po_percentage')->update(['value' => '60']);
    $users = User::count();
    $this->seed(ReferenceDataSeeder::class);
    $this->seed(ReferenceDataSeeder::class);
    expect(InventoryCategory::count())->toBe(12)
        ->and($room->fresh()->is_active)->toBeFalse()
        ->and($category->fresh()->is_active)->toBeFalse()
        ->and(StudioSetting::getSetting('po_percentage'))->toBe(60.0)
        ->and(User::count())->toBe($users);
});
