<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'Informe uma quantidade diferente de zero.',
            'quantity.integer' => 'Informe uma quantidade inteira.',
            'quantity.between' => 'A movimentação deve estar entre -1.000.000 e 1.000.000 unidades.',
            'reason.required' => 'Informe o motivo da movimentação.',
        ];
    }
}
