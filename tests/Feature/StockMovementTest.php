<?php

use App\Enums\TenantRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

test('stock entries and exits record the balance and responsible user', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $url = route('products.stock-movements.store', [$user->currentTenant, $product]);

    $this->actingAs($user)->post($url, ['quantity' => 10, 'reason' => 'Compra', 'idempotency_key' => Str::uuid()->toString()])->assertRedirect();
    $this->post($url, ['quantity' => -3, 'reason' => 'Venda', 'idempotency_key' => Str::uuid()->toString()])->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7]);
    $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => -3, 'balance_after' => 7, 'user_id' => $user->id, 'reason' => 'Venda']);
    $this->assertDatabaseCount('stock_movements', 2);
});

test('repeating a stock request does not apply it twice', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $data = ['quantity' => 10, 'reason' => 'Compra', 'idempotency_key' => Str::uuid()->toString()];
    $url = route('products.stock-movements.store', [$user->currentTenant, $product]);

    $this->actingAs($user)->post($url, $data)->assertRedirect();
    $this->post($url, $data)->assertRedirect();

    $this->assertDatabaseCount('stock_movements', 1);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
});

test('a reused idempotency key with different content is rejected', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $data = ['quantity' => 10, 'reason' => 'Compra', 'idempotency_key' => Str::uuid()->toString()];
    $url = route('products.stock-movements.store', [$user->currentTenant, $product]);
    $this->actingAs($user)->post($url, $data)->assertRedirect();

    $this->post($url, [...$data, 'quantity' => 20])->assertSessionHasErrors('idempotency_key');

    $this->assertDatabaseCount('stock_movements', 1);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
});

test('stock cannot become negative or overflow', function (int $initial, int $delta) {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create(['stock_quantity' => $initial]);

    $this->actingAs($user)->post(route('products.stock-movements.store', [$user->currentTenant, $product]), [
        'quantity' => $delta, 'reason' => 'Ajuste', 'idempotency_key' => Str::uuid()->toString(),
    ])->assertSessionHasErrors(['quantity' => 'A movimentação deve manter o estoque entre zero e 4.294.967.295 unidades.']);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => $initial]);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['insufficient stock' => [2, -3], 'overflow' => [4294967295, 1]]);

test('invalid stock movements are rejected', function (string $field, mixed $value, string $message) {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $data = ['quantity' => 1, 'reason' => 'Entrada', 'idempotency_key' => Str::uuid()->toString(), $field => $value];

    $this->actingAs($user)->post(route('products.stock-movements.store', [$user->currentTenant, $product]), $data)->assertSessionHasErrors([$field => $message]);

    $this->assertDatabaseCount('stock_movements', 0);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 0]);
})->with([
    'zero' => ['quantity', 0, 'Informe uma quantidade diferente de zero.'],
    'fraction' => ['quantity', 1.5, 'Informe uma quantidade inteira.'],
    'too large' => ['quantity', 1000001, 'A movimentação deve estar entre -1.000.000 e 1.000.000 unidades.'],
    'reason required' => ['reason', '', 'Informe o motivo da movimentação.'],
]);

test('another tenants stock cannot be adjusted', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)->post(route('products.stock-movements.store', [$user->currentTenant, $product]), [
        'quantity' => 1, 'reason' => 'Entrada', 'idempotency_key' => Str::uuid()->toString(),
    ])->assertNotFound();

    $this->assertDatabaseCount('stock_movements', 0);
});

test('members cannot adjust stock', function () {
    $user = User::factory()->create();
    $tenant = $user->currentTenant;
    $tenant->members()->updateExistingPivot($user->id, ['role' => TenantRole::Member->value]);
    $product = Product::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('products.stock-movements.store', [$tenant, $product]), [
        'quantity' => 1, 'reason' => 'Entrada', 'idempotency_key' => Str::uuid()->toString(),
    ])->assertForbidden();

    $this->assertDatabaseCount('stock_movements', 0);
});
