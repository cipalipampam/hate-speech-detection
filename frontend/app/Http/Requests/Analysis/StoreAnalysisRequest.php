<?php

namespace App\Http\Requests\Analysis;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalysisRequest extends FormRequest
{
    /**
     * Pastikan user punya permission untuk menjalankan analisis.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('run-analysis');
    }

    /**
     * Normalisasi input sebelum validasi dijalankan.
     * - Checkbox headless dari HTML form dikirim sebagai string "1" / null → convert ke bool
     * - Trim & deduplikasi keywords
     */
    protected function prepareForValidation(): void
    {
        // Normalisasi checkbox headless (HTML form mengirim "1" atau tidak mengirim sama sekali)
        $this->merge([
            'headless' => $this->boolean('headless'),
        ]);

        // Bersihkan keywords: trim, hapus kosong, deduplikasi, reset index
        if ($this->has('keywords') && is_array($this->keywords)) {
            $cleaned = collect($this->keywords)
                ->map(fn($kw) => trim((string) $kw))
                ->filter(fn($kw) => mb_strlen($kw) >= 2)
                ->unique()
                ->values()
                ->all();

            $this->merge(['keywords' => $cleaned]);
        }

        // Default max_scroll_steps jika tidak diisi
        if (!$this->filled('max_scroll_steps')) {
            $this->merge(['max_scroll_steps' => 300]);
        }
    }

    /**
     * Aturan validasi form pembuatan analisis baru.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'platform'         => ['required', 'in:x,threads,both'],
            'keywords'         => ['required', 'array', 'min:1', 'max:10'],
            'keywords.*'       => ['required', 'string', 'min:2', 'max:100'],
            'search_mode'      => ['required', 'in:latest,top'],
            'max_links'        => ['required', 'integer', 'min:5', 'max:500'],
            'max_scroll_steps' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'headless'         => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan kustom untuk validasi bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'title.required'      => 'Judul sesi analisis wajib diisi.',
            'title.max'           => 'Judul maksimal 255 karakter.',
            'platform.required'   => 'Pilih minimal satu platform target (X, Threads, atau Both).',
            'platform.in'         => 'Platform yang dipilih tidak valid. Gunakan: x, threads, atau both.',
            'keywords.required'   => 'Minimal satu kata kunci pencarian wajib dimasukkan.',
            'keywords.min'        => 'Masukkan minimal 1 kata kunci pencarian.',
            'keywords.max'        => 'Maksimal 10 kata kunci diperbolehkan.',
            'keywords.*.required' => 'Kata kunci tidak boleh kosong.',
            'keywords.*.min'      => 'Panjang kata kunci minimal 2 karakter.',
            'keywords.*.max'      => 'Panjang kata kunci maksimal 100 karakter.',
            'search_mode.required'=> 'Mode pencarian wajib dipilih.',
            'search_mode.in'      => 'Mode pencarian harus latest (terbaru) atau top (populer).',
            'max_links.required'  => 'Batas jumlah link postingan wajib ditentukan.',
            'max_links.integer'   => 'Batas link harus berupa angka.',
            'max_links.min'       => 'Batas link minimal 5 URL.',
            'max_links.max'       => 'Batas link maksimal 500 URL.',
            'max_scroll_steps.min'=> 'Batas scroll minimal 50 langkah.',
            'max_scroll_steps.max'=> 'Batas scroll maksimal 5000 langkah.',
        ];
    }

    /**
     * Custom attribute names untuk pesan error yang lebih ramah.
     */
    public function attributes(): array
    {
        return [
            'title'            => 'judul analisis',
            'platform'         => 'platform target',
            'keywords'         => 'kata kunci',
            'keywords.*'       => 'kata kunci',
            'search_mode'      => 'mode pencarian',
            'max_links'        => 'batas link',
            'max_scroll_steps' => 'batas scroll',
            'headless'         => 'mode headless',
        ];
    }
}

