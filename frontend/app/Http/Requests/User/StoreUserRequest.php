<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('manage-users');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'role'      => ['required', 'string', Rule::in(['admin', 'analyst'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama pengguna wajib diisi.',
            'email.required'     => 'Alamat email wajib diisi.',
            'email.unique'       => 'Email ini sudah terdaftar di sistem.',
            'password.required'  => 'Kata sandi awal wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role.required'      => 'Role pengguna wajib ditentukan.',
            'role.in'            => 'Role yang dipilih tidak valid (pilihan: admin, analyst).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'      => 'nama pengguna',
            'email'     => 'alamat email',
            'password'  => 'kata sandi',
            'role'      => 'peran pengguna',
            'is_active' => 'status aktif',
        ];
    }
}

