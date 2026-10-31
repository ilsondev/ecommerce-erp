<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    public function handle(Product $product, User $user, int $quantity, string $reason, string $idempotencyKey): StockMovement
    {
        return DB::transaction(function () use ($product, $user, $quantity, $reason, $idempotencyKey): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $existingMovement = $lockedProduct->stockMovements()->where('idempotency_key', $idempotencyKey)->first();

            if ($existingMovement) {
                if ($existingMovement->quantity !== $quantity || $existingMovement->reason !== $reason || $existingMovement->user_id !== $user->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Este identificador já foi usado em outra movimentação.']);
                }

                return $existingMovement;
            }

            $balance = $lockedProduct->stock_quantity + $quantity;

            if ($quantity === 0 || abs($quantity) > 1000000 || $balance < 0 || $balance > 4294967295) {
                throw ValidationException::withMessages(['quantity' => 'A movimentação deve manter o estoque entre zero e 4.294.967.295 unidades.']);
            }

            $movement = $lockedProduct->stockMovements()->create([
                'user_id' => $user->id,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            $lockedProduct->forceFill(['stock_quantity' => $balance])->save();

            return $movement;
        }, 3);
    }
}
