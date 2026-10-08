<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rapikan input sebelum divalidasi: email huruf kecil, dan nomor telepon
     * dijadikan format internasional tanpa plus (081234... menjadi 6281234...)
     * supaya siap dipakai untuk WhatsApp.
     */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => $digits,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^628\d{8,11}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // Pilihan eksplisit klien: true atau false. Bukan syarat mendaftar.
            'crm_consent' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar.',
            'phone.regex' => 'Gunakan nomor seluler Indonesia, misalnya 081234567890.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'crm_consent.required' => 'Pilihan persetujuan dihubungi kembali wajib diisi.',
        ];
    }
}