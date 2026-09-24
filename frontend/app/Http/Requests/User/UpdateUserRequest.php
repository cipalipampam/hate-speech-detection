<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user') ? (is_object($this->route('user')) ? $this->route('user')->id : $this->route('user')) : null;

        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'      => ['required', 'string', Rule::in(['admin', 'analyst'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique'   => 'Email ini sudah digunakan oleh akun lain.',
            'password.min'   => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'role.required'  => 'Role pengguna wajib ditentukan.',
            'role.in'        => 'Role yang dipilih tidak valid (pilihan: admin, analyst).',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'      => 'nama pengguna',
            'email'     => 'alamat email',
            'password'  => 'kata sandi baru',
            'password_confirmation' => 'konfirmasi kata sandi baru',
            'role'      => 'peran pengguna',
            'is_active' => 'status aktif',
        ];
    }
}

