<?php

namespace App\Http\Controllers;

use App\Actions\Products\CreateProductImageUpload;
use App\Http\Requests\CreateProductImageUploadRequest;
use App\Jobs\DeleteProductImage;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductImageController extends Controller
{
    public function store(CreateProductImageUploadRequest $request, Tenant $currentTenant, Product $product, CreateProductImageUpload $createUpload): JsonResponse
    {
        return response()->json($createUpload->handle($product, $request->validated('name'), $request->validated('mime_type'), $request->integer('size')), 201);
    }

    public function complete(Tenant $currentTenant, Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);

        DB::transaction(function () use ($image): void {
            $lockedImage = ProductImage::query()->lockForUpdate()->findOrFail($image->id);

            if ($lockedImage->status === 'pending') {
                $lockedImage->update(['status' => 'processing']);
                ProcessProductImage::dispatch($image->id);
            }
        });

        return to_route('products.edit', [$currentTenant, $product]);
    }

    public function destroy(Tenant $currentTenant, Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);

        DB::transaction(function () use ($image): void {
            $image->delete();
            DeleteProductImage::dispatch($image->id);
        });

        return to_route('products.edit', [$currentTenant, $product]);
    }
}
