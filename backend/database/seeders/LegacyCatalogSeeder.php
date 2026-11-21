<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\BasePrice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $file = __DIR__.'/legacy-catalog.json';
        $snapshot = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $source = 'local-tungku-catalog-2026-11-18';
        if (DB::table('catalog_imports')->where('source', $source)->exists()) {
            $this->command?->info('Legacy catalog already imported; subsequent manager edits are preserved.');

            return;
        }
        $manager = User::where('role', Role::Manager->value)->where('is_active', true)->first();
        if (! $manager) {
            throw new RuntimeException('Create an active manager before importing the legacy catalog.');
        }
        DB::transaction(function () use ($source, $file, $snapshot, $manager): void {
            DB::table('catalog_imports')->insert([
                'source' => $source, 'checksum' => hash_file('sha256', $file), 'imported_at' => now(),
            ]);
            foreach ($snapshot['base_prices'] as $row) {
                BasePrice::updateOrCreate(['stage' => $row['stage']], [
                    'unit' => $row['unit'], 'price' => $row['price'], 'updated_by' => $manager->id,
                ]);
            }
            foreach ($snapshot['packages'] as $row) {
                // Keep the stored effective price; do not recalculate historical prices.
                $package = Package::withTrashed()->where('name', $row['name'])->first();
                if ($package) {
                    continue;
                }
                $package = new Package;
                $package->fill($row);
                $package->created_by = $manager->id;
                $package->save();
            }
        });
        $this->command?->info('Imported '.count($snapshot['base_prices']).' legacy base prices and '.count($snapshot['packages']).' non-demo packages.');
        foreach (Package::whereIn('name', array_column($snapshot['packages'], 'name'))->get() as $package) {
            $this->command?->line($package->name.': Rp '.$package->price);
        }
    }
}
