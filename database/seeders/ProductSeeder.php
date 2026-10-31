<?php

namespace Database\Seeders;

use App\Actions\Products\RecordStockMovement;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(RecordStockMovement $recordStockMovement): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $tenant = Tenant::query()->where('is_personal', true)->first();
        $owner = $tenant?->owner();

        if (! $tenant || ! $owner instanceof User) {
            return;
        }

        Product::factory()->count(5)->for($tenant)->create()->each(function (Product $product) use ($owner, $recordStockMovement): void {
            $recordStockMovement->handle($product, $owner, 10, 'Estoque inicial de demonstração', Str::uuid()->toString());
        });
    }
}
