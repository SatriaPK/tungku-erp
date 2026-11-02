<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['required', 'string', 'regex:/^628\d{8,11}$/'],
            'current_password' => ['required_with:password', 'nullable', 'current_password:web'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah digunakan akun lain.',
            'phone.regex' => 'Gunakan nomor seluler Indonesia, misalnya 081234567890.',
            'current_password.current_password' => 'Kata sandi saat ini salah.',
        ];
    }
}
