<?php

use App\Enums\TenantRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests must sign in to access the catalog', function () {
    $tenant = Tenant::factory()->create();

    $this->get(route('products.index', $tenant))->assertRedirect(route('login'));
});

test('the catalog only contains products from the tenant in the URL', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    Product::factory()->create();

    $this->actingAs($user)->get(route('products.index', $user->currentTenant))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('products/index')
        ->has('products.data', 1)->where('products.data.0.id', $product->id)->where('canManage', true));
});

test('owners can create products without setting the tenant or stock from user input', function () {
    $user = User::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->post(route('products.store', $user->currentTenant), [
        'sku' => 'CAMISETA-01', 'name' => 'Camiseta', 'price' => '39.90',
        'tenant_id' => $otherTenant->id, 'stock_quantity' => 999,
    ]);

    $product = Product::query()->sole();
    $response->assertRedirect(route('products.edit', [$user->currentTenant, $product]));
    $this->assertDatabaseHas('products', ['id' => $product->id, 'tenant_id' => $user->current_tenant_id, 'stock_quantity' => 0, 'sku' => 'CAMISETA-01']);
});

test('the create and edit product pages can be rendered', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();

    $this->actingAs($user)->get(route('products.create', $user->currentTenant))->assertOk();
    $this->get(route('products.edit', [$user->currentTenant, $product]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('products/edit')->where('product.id', $product->id));
});

test('product edits preserve stock and ownership', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create(['stock_quantity' => 7]);

    $this->actingAs($user)->patch(route('products.update', [$user->currentTenant, $product]), [
        'sku' => $product->sku, 'name' => 'Novo nome', 'price' => '42.10', 'stock_quantity' => 100, 'tenant_id' => 999,
    ])->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Novo nome', 'stock_quantity' => 7, 'tenant_id' => $user->current_tenant_id]);
});

test('required product fields have actionable errors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('products.store', $user->currentTenant), [])
        ->assertSessionHasErrors(['sku' => 'Informe o SKU do produto.', 'name' => 'Informe o nome do produto.', 'price' => 'Informe o preço de venda.']);

    $this->assertDatabaseCount('products', 0);
});

test('invalid product values are rejected without saving', function (string $field, mixed $value, string $message) {
    $user = User::factory()->create();
    $data = ['sku' => 'SKU-01', 'name' => 'Produto', 'price' => '10.00', $field => $value];

    $this->actingAs($user)->post(route('products.store', $user->currentTenant), $data)->assertSessionHasErrors([$field => $message]);

    $this->assertDatabaseCount('products', 0);
})->with([
    'negative price' => ['price', '-1', 'O preço não pode ser negativo.'],
    'fractional cent' => ['price', '1.999', 'Use no máximo duas casas decimais no preço.'],
    'invalid barcode' => ['barcode', 'abcdefgh', 'Informe um código de barras com 8 a 14 dígitos.'],
]);

test('a SKU is reserved within its tenant even after archiving', function () {
    $user = User::factory()->create();
    Product::factory()->for($user->currentTenant)->archived()->create(['sku' => 'RESERVADO']);

    $this->actingAs($user)->post(route('products.store', $user->currentTenant), [
        'sku' => 'RESERVADO', 'name' => 'Produto', 'price' => '10',
    ])->assertSessionHasErrors(['sku' => 'Este SKU já está em uso neste tenant, inclusive em produtos arquivados.']);
});

test('different tenants can use the same SKU', function () {
    $user = User::factory()->create();
    Product::factory()->create(['sku' => 'COMPARTILHADO']);

    $this->actingAs($user)->post(route('products.store', $user->currentTenant), [
        'sku' => 'COMPARTILHADO', 'name' => 'Produto', 'price' => '10',
    ])->assertRedirect();

    $this->assertDatabaseCount('products', 2);
});

test('products cannot be read or changed through another tenants URL', function (string $method, string $routeName) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Privado']);

    $this->actingAs($user)->{$method}(route($routeName, [$user->currentTenant, $product]), [
        'sku' => 'INVASOR', 'name' => 'Alterado', 'price' => '1',
    ])->assertNotFound();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Privado', 'deleted_at' => null]);
})->with([
    'edit' => ['get', 'products.edit'], 'update' => ['patch', 'products.update'],
    'archive' => ['delete', 'products.destroy'], 'restore' => ['post', 'products.restore'],
]);

test('tenant members can view products but cannot edit them', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->members()->attach($user, ['role' => TenantRole::Member->value]);
    $product = Product::factory()->for($tenant)->create();

    $this->actingAs($user)->get(route('products.edit', [$tenant, $product]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->patch(route('products.update', [$tenant, $product]), ['sku' => $product->sku, 'name' => 'Alterado', 'price' => '1'])->assertForbidden();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $product->name]);
});

test('owners can archive and restore products without losing stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create(['stock_quantity' => 12]);

    $this->actingAs($user)->delete(route('products.destroy', [$user->currentTenant, $product]))->assertRedirect(route('products.index', $user->currentTenant));
    $this->assertSoftDeleted($product);
    $this->post(route('products.restore', [$user->currentTenant, $product]))->assertRedirect(route('products.edit', [$user->currentTenant, $product]));
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 12, 'deleted_at' => null]);
});

test('searching respects the tenant boundary for both name and SKU', function () {
    $user = User::factory()->create();
    Product::factory()->create(['sku' => 'SEGREDO', 'name' => 'Segredo']);
    Product::factory()->for($user->currentTenant)->create(['name' => 'Outro produto']);

    $this->actingAs($user)->get(route('products.index', [$user->currentTenant, 'search' => 'SEGREDO']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});
