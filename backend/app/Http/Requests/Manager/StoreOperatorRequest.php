<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => $digits,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^628\d{8,11}$/'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar.',
            'phone.regex' => 'Gunakan nomor seluler Indonesia, misalnya 081234567890.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
