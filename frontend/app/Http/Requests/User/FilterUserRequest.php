<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi filter daftar pengguna pada halaman Kelola Pengguna.
 *
 * Sebelumnya `UserManagementService` membaca objek Request HTTP secara langsung
 * (satu-satunya filter yang belum divalidasi). Sekarang filter divalidasi di sini
 * dan service hanya menerima data bersih.
 */
class FilterUserRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:200'],
            'role'   => ['nullable', 'string', Rule::in(['all', 'admin', 'analyst'])],
            // 'aktif'/'nonaktif'/'1'/'0' dipertahankan agar URL lama tetap valid.
            'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive', 'aktif', 'nonaktif', '1', '0'])],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'Kata kunci pencarian maksimal 200 karakter.',
            'role.in'    => 'Peran yang dipilih tidak valid (pilihan: all, admin, analyst).',
            'status.in'  => 'Status yang dipilih tidak valid (pilihan: all, active, inactive).',
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'kata kunci pencarian',
            'role'   => 'filter peran',
            'status' => 'filter status',
        ];
    }
}
