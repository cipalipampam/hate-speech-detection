<?php

namespace App\Http\Requests\Analysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi query string filter POSTINGAN pada halaman detail analisis (`analyses.show`).
 *
 * Berbeda dari `FilterAnalysisRequest` yang menyaring daftar SESI analisis di halaman index.
 * Nama lama (`FilterAnalysisPostRequest`) membingungkan karena route-nya memakai method GET,
 * sehingga menyiratkan "POST request" — padahal yang dimaksud adalah "postingan".
 */
class FilterPostRequest extends FormRequest
{
    /**
     * Izinkan semua user terautentikasi yang bisa melihat dashboard.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('view-dashboard');
    }

    /**
     * Aturan validasi query string filter postingan.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform'   => ['nullable', 'string', 'in:all,x,threads,X,Threads'],
            'label_lvl1' => ['nullable', 'string', 'in:all,hate_speech,non_hate_speech'],
            'label_lvl2' => ['nullable', 'string', 'in:all,tidak_relevan,delegitimasi_institusi,dehumanisasi,ajakan_kekerasan,hoaks_pemicu_kebencian,hoax_pemicu_kebencian,kutukan_agama_personal'],
            'search'     => ['nullable', 'string', 'max:200'],
            'per_page'   => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    /**
     * Pesan validasi multibahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'platform.in'      => 'Platform yang dipilih tidak valid (pilihan: all, x, threads).',
            'label_lvl1.in'    => 'Kategori Sentimen Level 1 tidak valid.',
            'label_lvl2.in'    => 'Kategori Sub-kelas Level 2 tidak valid.',
            'search.max'       => 'Kata kunci pencarian maksimal 200 karakter.',
            'per_page.between' => 'Jumlah data per halaman harus antara 5 dan 100.',
        ];
    }

    /**
     * Label atribut khusus.
     */
    public function attributes(): array
    {
        return [
            'platform'   => 'platform media sosial',
            'label_lvl1' => 'sentimen level 1',
            'label_lvl2' => 'kategori level 2',
            'search'     => 'kata kunci pencarian',
            'per_page'   => 'jumlah baris per halaman',
        ];
    }
}
