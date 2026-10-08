<?php

namespace App\Actions\Auth;

use App\Enum\Role;
use App\Models\User;

class RegisterClient
{
    /**
     * Membuat akun klien. Peran diset eksplisit di sini, tidak pernah
     * diambil dari input pengguna.
     *
     * @param  array{name: string, email: string, phone: string, password: string, crm_consent: bool}  $data
     */
    public function handle(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'crm_consent_at' => $data['crm_consent'] ? now() : null,
        ]);

        $user->role = Role::Client;
        $user->is_active = true;
        $user->save();

        return $user;
    }
}