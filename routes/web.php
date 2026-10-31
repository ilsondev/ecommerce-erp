<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\Tenants\TenantInvitationController;
use App\Http\Middleware\EnsureTenantMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_tenant}')
    ->middleware(['auth', 'verified', EnsureTenantMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::resource('products', ProductController::class)->except('show');
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
            Route::post('products/{product}/stock-movements', [StockMovementController::class, 'store'])->name('products.stock-movements.store');
            Route::post('products/{product}/images', [ProductImageController::class, 'store'])->middleware('throttle:30,1')->name('products.images.store');
            Route::post('products/{product}/images/{image}/complete', [ProductImageController::class, 'complete'])->name('products.images.complete');
            Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TenantInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TenantInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
