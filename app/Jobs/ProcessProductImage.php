<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessProductImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 25;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(public int $imageId)
    {
        $this->onQueue('images');
    }

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('product-image:'.$this->imageId))->shared()->releaseAfter(60)->expireAfter(60)];
    }

    public function handle(): void
    {
        $image = ProductImage::find($this->imageId);

        if (! $image) {
            return;
        }

        $disk = Storage::disk('r2');

        if (in_array($image->status, ['ready', 'failed'], true)) {
            $disk->delete($image->upload_path);

            return;
        }

        $stream = $disk->readStream($image->upload_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        try {
            $contents = stream_get_contents($stream, 5242881);
        } finally {
            fclose($stream);
        }

        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        $actualSize = strlen($contents);

        if ($actualSize !== $image->size || $actualSize > 5242880 || $actualSize < 1) {
            $image->update(['status' => 'failed', 'error' => 'O tamanho do arquivo é inválido ou excede 5 MB.']);
            $disk->delete($image->upload_path);

            return;
        }

        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false || $dimensions['mime'] !== $image->mime_type || $dimensions[0] > 10000 || $dimensions[1] > 10000) {
            $image->update(['status' => 'failed', 'error' => 'Envie uma imagem JPEG, PNG ou WebP válida, com até 10.000 pixels por dimensão.']);
            $disk->delete($image->upload_path);

            return;
        }

        $disk->put($image->path, $contents, ['ContentType' => $image->mime_type]);
        $image->update(['status' => 'ready', 'width' => $dimensions[0], 'height' => $dimensions[1], 'error' => null]);
        $disk->delete($image->upload_path);
    }

    public function failed(?Throwable $exception): void
    {
        ProductImage::query()->whereKey($this->imageId)->where('status', 'processing')->update([
            'status' => 'failed',
            'error' => 'Não foi possível processar a imagem. Remova-a e tente enviar novamente.',
        ]);
    }
}
