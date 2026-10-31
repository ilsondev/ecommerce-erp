<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $identifier = Str::uuid()->toString();

        return [
            'product_id' => Product::factory(),
            'path' => 'products/'.$identifier.'.png',
            'upload_path' => 'uploads/'.$identifier.'.png',
            'original_name' => 'produto.png',
            'mime_type' => 'image/png',
            'size' => 100,
            'status' => 'pending',
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => ['status' => 'processing']);
    }

    public function ready(): static
    {
        return $this->state(fn (): array => ['status' => 'ready', 'width' => 100, 'height' => 100]);
    }
}
