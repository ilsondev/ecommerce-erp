<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\TenantInvitation;
use App\Models\User;

test('the tenant migration preserves existing records and can be reversed', function () {
    $user = User::factory()->create();
    $tenant = $user->currentTenant;
    $product = Product::factory()->for($tenant)->create(['stock_quantity' => 10]);
    $movement = StockMovement::factory()->for($product)->create(['user_id' => $user->id]);
    $invitation = TenantInvitation::factory()->for($tenant)->create(['invited_by' => $user->id]);
    $image = ProductImage::factory()->for($product)->ready()->create([
        'path' => 'teams/'.$tenant->id.'/products/'.$product->id.'/original.png',
    ]);
    $migration = require database_path('migrations/2026_10_31_190753_rename_teams_to_tenants.php');

    $migration->down();

    $this->assertDatabaseHas('teams', ['id' => $tenant->id, 'slug' => $tenant->slug]);
    $this->assertDatabaseHas('team_members', ['team_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner']);
    $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id, 'team_id' => $tenant->id]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'current_team_id' => $tenant->id]);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'team_id' => $tenant->id, 'stock_quantity' => 10]);

    $migration->up();

    expect($user->fresh()->currentTenant->id)->toBe($tenant->id);
    expect($user->fresh()->belongsToTenant($tenant))->toBeTrue();
    expect($product->fresh()->tenant->id)->toBe($tenant->id);
    $this->assertDatabaseHas('tenant_invitations', ['id' => $invitation->id, 'tenant_id' => $tenant->id, 'code' => $invitation->code]);
    $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'product_id' => $product->id, 'balance_after' => 10]);
    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'path' => 'teams/'.$tenant->id.'/products/'.$product->id.'/original.png']);
});
