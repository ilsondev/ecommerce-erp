<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', Rule::in(['image/jpeg', 'image/png', 'image/webp'])],
            'size' => ['required', 'integer', 'between:1,5242880'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mime_type.in' => 'Envie uma imagem JPEG, PNG ou WebP.',
            'size.between' => 'A imagem deve ter no máximo 5 MB.',
        ];
    }
}
