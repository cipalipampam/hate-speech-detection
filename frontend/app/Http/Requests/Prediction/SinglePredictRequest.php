<?php

namespace App\Http\Requests\Prediction;

use Illuminate\Foundation\Http\FormRequest;

class SinglePredictRequest extends FormRequest
{
    /**
     * Pastikan user memiliki izin untuk melakukan live text prediction.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('test-single-prediction');
    }

    /**
     * Aturan validasi teks opini.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text'       => ['required', 'string', 'min:3', 'max:2000'],
            'preprocess' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan validasi.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Kalimat teks opini wajib diisi untuk diuji.',
            'text.min'      => 'Panjang teks minimal 3 karakter.',
            'text.max'      => 'Panjang teks maksimal 2.000 karakter.',
        ];
    }
}
