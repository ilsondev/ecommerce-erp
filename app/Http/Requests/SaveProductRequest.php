<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', [Product::class, $this->route('current_tenant')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('current_tenant');

        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->where('tenant_id', $tenant->id)->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'barcode' => ['nullable', 'regex:/^\d{8,14}$/'],
            'brand' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sku.required' => 'Informe o SKU do produto.',
            'sku.unique' => 'Este SKU já está em uso neste tenant, inclusive em produtos arquivados.',
            'name.required' => 'Informe o nome do produto.',
            'price.required' => 'Informe o preço de venda.',
            'price.decimal' => 'Use no máximo duas casas decimais no preço.',
            'price.min' => 'O preço não pode ser negativo.',
            'barcode.regex' => 'Informe um código de barras com 8 a 14 dígitos.',
        ];
    }
}
