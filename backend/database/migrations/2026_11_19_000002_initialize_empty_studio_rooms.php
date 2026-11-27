<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Match RoomSeeder's existing studio configuration. Preserve all
        // existing rooms, including rooms deliberately marked inactive.
        if (! DB::table('rooms')->exists()) {
            DB::table('rooms')->insert([
                'name' => 'Studio Utama',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep operational room data and any bookings referencing it.
    }
};
