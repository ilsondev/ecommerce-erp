<?php

use App\Enums\TenantRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('catalog permissions follow the tenant role', function (TenantRole $role, bool $canManage) {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->members()->attach($user, ['role' => $role->value]);
    $product = Product::factory()->for($tenant)->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', [Product::class, $tenant]))->toBeTrue();
    expect($gate->allows('create', [Product::class, $tenant]))->toBe($canManage);
    expect($gate->allows('update', $product))->toBe($canManage);
    expect($gate->allows('delete', $product))->toBe($canManage);
})->with([
    'owner' => [TenantRole::Owner, true], 'admin' => [TenantRole::Admin, true], 'member' => [TenantRole::Member, false],
]);

test('non members have no catalog permissions', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', [Product::class, $product->tenant]))->toBeFalse();
    expect(Gate::forUser($user)->allows('create', [Product::class, $product->tenant]))->toBeFalse();
    expect(Gate::forUser($user)->allows('update', $product))->toBeFalse();
    expect(Gate::forUser($user)->allows('delete', $product))->toBeFalse();
});
