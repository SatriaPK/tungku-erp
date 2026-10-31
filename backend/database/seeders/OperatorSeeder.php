<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class OperatorSeeder extends Seeder
{
    /**
     * Create the demo operator accounts used by the Manager operator page.
     */
    public function run(): void
    {
        $password = env('SEED_DEFAULT_PASSWORD');

        if ($password === null) {
            if (app()->isProduction()) {
                throw new RuntimeException('Isi SEED_DEFAULT_PASSWORD di .env sebelum menjalankan OperatorSeeder di produksi.');
            }

            $password = 'password';
        }

        foreach ([
            ['Operator Satu', 'operator1@studio.test', '6281200000002'],
            ['Operator Dua', 'operator2@studio.test', '6281200000003'],
            ['Operator Tiga', 'operator3@studio.test', '6281200000005'],
            ['Operator Empat', 'operator4@studio.test', '6281200000006'],
            ['Operator Lima', 'operator5@studio.test', '6281200000007'],
            ['Operator Enam', 'operator6@studio.test', '6281200000008'],
        ] as [$name, $email, $phone]) {
            $operator = User::firstOrNew(['email' => $email]);
            $operator->fill([
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
            ]);
            $operator->role = Role::Operator;
            $operator->is_active = true;
            $operator->email_verified_at ??= now();
            $operator->crm_consent_at = null;
            $operator->save();
        }
    }
}