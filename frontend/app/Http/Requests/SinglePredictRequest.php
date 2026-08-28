<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SinglePredictRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('test-single-prediction');
    }

    /**
     * Get the validation rules that apply to the request.
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
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Teks kalimat opini wajib diisi untuk diuji.',
            'text.min'      => 'Teks minimal terdiri dari 3 karakter.',
            'text.max'      => 'Teks maksimal 2.000 karakter.',
        ];
    }
}
