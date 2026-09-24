<?php

namespace App\Http\Requests\Analysis;

use Illuminate\Foundation\Http\FormRequest;

class FilterAnalysisRequest extends FormRequest
{
    /**
     * Izinkan semua pengguna yang memiliki hak akses melihat dashboard/laporan.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('view-dashboard');
    }

    /**
     * Aturan validasi query string filter daftar sesi analisis.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:200'],
            'platform' => ['nullable', 'string', 'in:all,x,threads,both,X,Threads,Both'],
            'status'   => ['nullable', 'string', 'in:all,queued,running,completed,failed'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    /**
     * Pesan validasi multibahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'search.max'    => 'Kata kunci pencarian maksimal 200 karakter.',
            'platform.in'   => 'Platform yang dipilih tidak valid (pilihan: all, x, threads, both).',
            'status.in'     => 'Status analisis tidak valid (pilihan: all, queued, running, completed, failed).',
            'per_page.between' => 'Jumlah data per halaman harus antara 5 dan 100.',
        ];
    }

    /**
     * Label atribut khusus.
     */
    public function attributes(): array
    {
        return [
            'search'   => 'kata kunci pencarian',
            'platform' => 'filter platform',
            'status'   => 'filter status',
            'per_page' => 'jumlah data per halaman',
        ];
    }
}
