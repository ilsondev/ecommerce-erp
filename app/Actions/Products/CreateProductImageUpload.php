<?php

namespace App\Actions\Products;

use App\Jobs\ClearProductImageUpload;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductImageUpload
{
    /** @return array{id: int, url: string, headers: array<string, mixed>} */
    public function handle(Product $product, string $name, string $mimeType, int $size): array
    {
        if (blank(config('filesystems.disks.r2.bucket')) || blank(config('filesystems.disks.r2.key'))
            || blank(config('filesystems.disks.r2.secret')) || blank(config('filesystems.disks.r2.endpoint'))) {
            throw ValidationException::withMessages(['image' => 'Configure o armazenamento R2 antes de enviar imagens.']);
        }

        return DB::transaction(function () use ($product, $name, $mimeType, $size): array {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($lockedProduct->images()->count() >= 10) {
                throw ValidationException::withMessages(['image' => 'Cada produto pode ter até 10 imagens. Remova uma antes de enviar outra.']);
            }

            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw ValidationException::withMessages(['mime_type' => 'Envie uma imagem JPEG, PNG ou WebP.']),
            };
            $identifier = Str::uuid()->toString();
            $directory = 'tenants/'.$lockedProduct->tenant_id.'/products/'.$lockedProduct->id;
            $image = $lockedProduct->images()->create([
                'path' => $directory.'/'.$identifier.'.'.$extension,
                'upload_path' => 'uploads/'.$directory.'/'.$identifier.'.'.$extension,
                'original_name' => $name,
                'mime_type' => $mimeType,
                'size' => $size,
            ]);
            ClearProductImageUpload::dispatch($image->id, $image->upload_path)->delay(now()->addMinutes(10));
            $upload = Storage::disk('r2')->temporaryUploadUrl($image->upload_path, now()->addMinutes(5), ['ContentType' => $mimeType]);

            return ['id' => $image->id, 'url' => $upload['url'], 'headers' => $upload['headers']];
        });
    }
}
