<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ClearProductImageUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 25;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(public int $imageId, public string $uploadPath)
    {
        $this->onQueue('images');
    }

    public function handle(): void
    {
        $image = ProductImage::withTrashed()->find($this->imageId);

        if (! $image || $image->trashed() || in_array($image->status, ['ready', 'failed'], true)) {
            Storage::disk('r2')->delete($this->uploadPath);
        }
    }
}
