<?php

namespace App\Http\Requests\Analysis;

use Illuminate\Foundation\Http\FormRequest;

class FilterAnalysisPostRequest extends FormRequest
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
            'per_page'   => ['nullable', 'integer', 'in:10,25,50,100'],
        ];
    }
}
