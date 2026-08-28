<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('run-analysis');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'platform'         => ['required', 'in:x,threads,both'],
            'keywords'         => ['required', 'array', 'min:1'],
            'keywords.*'       => ['required', 'string', 'min:2', 'max:100'],
            'search_mode'      => ['required', 'in:latest,top'],
            'max_links'        => ['required', 'integer', 'min:5', 'max:500'],
            'max_scroll_steps' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'headless'         => ['nullable', 'boolean'],
            'export_csv'       => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'title.required'      => 'Judul sesi analisis wajib diisi.',
            'platform.required'   => 'Pilih minimal satu platform target (X, Threads, atau Both).',
            'platform.in'         => 'Platform yang dipilih tidak valid.',
            'keywords.required'   => 'Minimal satu kata kunci pencarian wajib dimasukkan.',
            'keywords.min'        => 'Masukkan minimal 1 keyword pencarian.',
            'keywords.*.required' => 'Kata kunci tidak boleh kosong.',
            'keywords.*.min'      => 'Panjang kata kunci minimal 2 karakter.',
            'search_mode.in'      => 'Mode pencarian harus latest (terbaru) atau top (populer).',
            'max_links.min'       => 'Batas link minimal 5 URL.',
            'max_links.max'       => 'Batas link maksimal 500 URL.',
        ];
    }
}
