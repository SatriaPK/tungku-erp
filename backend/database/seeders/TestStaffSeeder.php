<?php

namespace Database\Seeders;

use App\Enum\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestStaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TEST_STAFF_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set TEST_STAFF_PASSWORD to at least 12 characters in the hosting secrets.');
        }

        DB::transaction(function () use ($password): void {
            foreach ([
                ['Manager Tes', 'manager@studio.test', '6281200000001', Role::Manager],
                ['Operator Tes', 'operator1@studio.test', '6281200000002', Role::Operator],
            ] as [$name, $email, $phone, $role]) {
                // Repeated deploys must never reset or elevate an existing account.
                if (User::where('email', $email)->exists()) {
                    continue;
                }
                $user = new User;
                $user->name = $name;
                $user->email = $email;
                $user->phone = $phone;
                $user->password = $password;
                $user->role = $role;
                $user->is_active = true;
                $user->email_verified_at = now();
                $user->save();
            }
        });
    }
}
