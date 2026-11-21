<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Wajib ada: hold slot Recording mengunci baris ruangan ini.
        Room::firstOrCreate(
            ['name' => 'Studio Utama'],
            ['is_active' => true],
        );
    }
}
