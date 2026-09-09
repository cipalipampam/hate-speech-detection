<?php

namespace App\Http\Requests\Scraper;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerLoginRequest extends FormRequest
{
    /**
     * Pastikan pengguna memiliki izin untuk mengelola sesi otentikasi browser scraper.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('manage-auth-sessions');
    }

    /**
     * Siapkan route parameter 'platform' untuk divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform' => strtolower((string) ($this->route('platform') ?? $this->input('platform'))),
        ]);
    }

    /**
     * Pastikan parameter route 'platform' masuk ke dalam data validasi.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'platform' => strtolower((string) ($this->route('platform') ?? $this->input('platform'))),
        ]);
    }

    /**
     * Aturan validasi request login scraper.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(['x', 'threads'])],
        ];
    }

    /**
     * Pesan kesalahan validasi kustom.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform.required' => 'Platform scraper wajib ditentukan.',
            'platform.in'       => 'Platform tidak valid. Pilih X atau Threads.',
        ];
    }

    /**
     * Helper untuk mendapatkan platform yang telah tervalidasi.
     */
    public function getPlatform(): string
    {
        return (string) $this->validated('platform');
    }

    /**
     * Helper untuk mendapatkan label tampilan platform yang ramah pengguna.
     */
    public function getPlatformDisplayName(): string
    {
        return $this->getPlatform() === 'x' ? 'Twitter (X)' : 'Threads';
    }
}
