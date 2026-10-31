<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('teams', 'tenants');
        Schema::rename('team_members', 'tenant_members');
        Schema::rename('team_invitations', 'tenant_invitations');

        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->renameColumn('team_id', 'tenant_id');
        });

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->renameColumn('team_id', 'tenant_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('team_id', 'tenant_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('current_team_id', 'current_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('current_tenant_id', 'current_team_id');
        });

        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'team_id');
        });

        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'team_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'team_id');
        });

        Schema::rename('tenant_invitations', 'team_invitations');
        Schema::rename('tenant_members', 'team_members');
        Schema::rename('tenants', 'teams');
    }
};
