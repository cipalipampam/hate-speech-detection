<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'         => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok dengan data kami.',
            'password.required'                 => 'Kata sandi baru wajib diisi.',
            'password.min'                      => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed'                => 'Konfirmasi kata sandi baru tidak cocok.',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password'      => 'kata sandi saat ini',
            'password'              => 'kata sandi baru',
            'password_confirmation' => 'konfirmasi kata sandi baru',
        ];
    }
}
