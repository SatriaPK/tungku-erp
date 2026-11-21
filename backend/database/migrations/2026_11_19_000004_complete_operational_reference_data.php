<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'inventory_categories' => ['Mikrofon', 'Audio interface', 'Monitor dan headphone', 'Komputer dan perangkat', 'Kabel dan aksesori', 'Furnitur dan akustik ruangan', 'Instrumen musik', 'Pencahayaan studio', 'Kamera dan video', 'Perangkat lunak dan lisensi', 'Penyimpanan dan media', 'Daya dan kelistrikan'],
            'expense_categories' => ['Sewa Studio', 'Listrik dan Air', 'Internet dan Telepon', 'Perlengkapan Studio', 'Perawatan Peralatan', 'Kebersihan', 'Konsumsi', 'Pemasaran', 'Lain-lain'],
        ];
        foreach ($categories as $table => $names) {
            foreach ($names as $name) {
                if (! DB::table($table)->where('name', $name)->exists()) {
                    DB::table($table)->insert(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        foreach (['studio_profit_percentage' => '50.00', 'po_percentage' => '50'] as $key => $value) {
            if (! DB::table('studio_settings')->where('key', $key)->exists()) {
                DB::table('studio_settings')->insert(['key' => $key, 'value' => $value, 'type' => 'decimal', 'description' => $key === 'po_percentage' ? 'Persentase pembayaran PO (Down Payment).' : 'Persentase laba bersih positif untuk Studio.', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Preserve reference rows that may already be used by transactions.
    }
};
