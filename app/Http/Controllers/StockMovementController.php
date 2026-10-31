<?php

namespace App\Http\Controllers;

use App\Actions\Products\RecordStockMovement;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    public function store(StoreStockMovementRequest $request, Tenant $currentTenant, Product $product, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $recordStockMovement->handle($product, $request->user(), $request->integer('quantity'), $request->validated('reason'), $request->validated('idempotency_key'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estoque atualizado.']);

        return to_route('products.edit', [$currentTenant, $product]);
    }
}
