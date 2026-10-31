<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

class DeleteProductImage implements ShouldQueue
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
        $image = ProductImage::onlyTrashed()->find($this->imageId);

        if (! $image) {
            return;
        }

        Storage::disk('r2')->delete([$image->path, $image->upload_path]);
        $image->forceDelete();
    }
}
