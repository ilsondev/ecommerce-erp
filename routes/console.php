<?php

use App\Models\TenantInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TenantInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired tenant invitations');

Schedule::command('queue:work database --queue=marketplaces,images,default --stop-when-empty --max-time=40 --timeout=25 --tries=5 --sleep=1 --memory=128')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('products:prune-images')->hourly()->withoutOverlapping(5);
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();
