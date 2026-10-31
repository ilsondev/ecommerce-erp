<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, Tenant $currentTenant): Response
    {
        Gate::authorize('viewAny', [Product::class, $currentTenant]);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'boolean']]);
        $search = $filters['search'] ?? '';
        $archived = $request->boolean('archived');

        return Inertia::render('products/index', [
            'products' => $currentTenant->products()
                ->when($archived, fn ($query) => $query->onlyTrashed())
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')))
                ->orderByDesc('id')
                ->paginate(20, ['id', 'sku', 'name', 'brand', 'price', 'stock_quantity', 'deleted_at'])
                ->withQueryString(),
            'filters' => ['search' => $search, 'archived' => $archived],
            'canManage' => $request->user()->can('create', [Product::class, $currentTenant]),
        ]);
    }

    public function create(Tenant $currentTenant): Response
    {
        Gate::authorize('create', [Product::class, $currentTenant]);

        return Inertia::render('products/create');
    }

    public function store(SaveProductRequest $request, Tenant $currentTenant): RedirectResponse
    {
        $product = $currentTenant->products()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto cadastrado.']);

        return to_route('products.edit', [$currentTenant, $product]);
    }

    public function edit(Request $request, Tenant $currentTenant, Product $product): Response
    {
        Gate::authorize('viewAny', [Product::class, $currentTenant]);
        $product->load('images');

        return Inertia::render('products/edit', [
            'product' => $product->only(['id', 'sku', 'name', 'description', 'barcode', 'brand', 'price', 'stock_quantity']),
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'name' => $image->original_name,
                'status' => $image->status,
                'error' => $image->error,
                'url' => $image->status === 'ready' ? Storage::disk('r2')->temporaryUrl($image->path, now()->addMinutes(20)) : null,
            ]),
            'movements' => $product->stockMovements()->with('user:id,name')->orderByDesc('id')->limit(50)->get()
                ->map(fn (StockMovement $movement) => [
                    'id' => $movement->id,
                    'quantity' => $movement->quantity,
                    'balance_after' => $movement->balance_after,
                    'reason' => $movement->reason,
                    'user' => $movement->user->name ?? 'Usuário removido',
                    'created_at' => $movement->created_at->toIso8601String(),
                ]),
            'canManage' => $request->user()->can('update', $product),
            'imageStorageConfigured' => filled(config('filesystems.disks.r2.key')) && filled(config('filesystems.disks.r2.secret'))
                && filled(config('filesystems.disks.r2.bucket')) && filled(config('filesystems.disks.r2.endpoint')),
        ]);
    }

    public function update(SaveProductRequest $request, Tenant $currentTenant, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto atualizado.']);

        return to_route('products.edit', [$currentTenant, $product]);
    }

    public function destroy(Tenant $currentTenant, Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto arquivado. O histórico e as imagens foram preservados.']);

        return to_route('products.index', $currentTenant);
    }

    public function restore(Tenant $currentTenant, Product $product): RedirectResponse
    {
        abort_if($currentTenant->trashed(), 404);
        Gate::authorize('update', $product);
        $product->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto restaurado.']);

        return to_route('products.edit', [$currentTenant, $product]);
    }
}
