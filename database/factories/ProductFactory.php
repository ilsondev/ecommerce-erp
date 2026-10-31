<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'brand' => fake()->company(),
            'price' => fake()->randomFloat(2, 1, 10000),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
