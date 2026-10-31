<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'quantity' => 10,
            'balance_after' => 10,
            'reason' => 'Entrada inicial',
            'idempotency_key' => Str::uuid()->toString(),
        ];
    }
}
