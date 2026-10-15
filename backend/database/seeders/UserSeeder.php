<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_DEFAULT_PASSWORD');

        if ($password === null) {
            if (app()->isProduction()) {
                throw new RuntimeException('Isi SEED_DEFAULT_PASSWORD di .env sebelum menjalankan seeder di produksi.');
            }

            $password = 'password';
        }

        $accounts = [
            ['Manager Studio', 'manager@studio.test', '6281200000001', Role::Manager],
            ['Operator Satu', 'operator1@studio.test', '6281200000002', Role::Operator],
            ['Operator Dua', 'operator2@studio.test', '6281200000003', Role::Operator],
            ['Klien Contoh', 'klien@studio.test', '6281200000004', Role::Client],
        ];

        foreach ($accounts as [$name, $email, $phone, $role]) {
            $user = User::firstOrNew(['email' => $email]);

            $user->fill([
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
            ]);

            // role dan is_active tidak masuk $fillable, jadi diset eksplisit
            $user->role = $role;
            $user->is_active = true;
            $user->email_verified_at ??= now();

            if ($role === Role::Client) {
                $user->crm_consent_at ??= now();
            }

            $user->save();
        }
    }
}
