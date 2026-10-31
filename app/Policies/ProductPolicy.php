<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->belongsToTenant($tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenantRole($tenant)?->isAtLeast(TenantRole::Admin) ?? false;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user, $product->tenant);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
