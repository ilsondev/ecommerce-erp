<?php

namespace App\Actions\Tenants;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTenant
{
    /**
     * Create a new tenant and add the user as owner.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Tenant
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $tenant = Tenant::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $membership = $tenant->memberships()->create([
                'user_id' => $user->id,
                'role' => TenantRole::Owner,
            ]);

            $user->switchTenant($tenant);

            return $tenant;
        });
    }
}
