<?php

namespace App\Console\Commands;

use App\Jobs\DeleteProductImage;
use App\Jobs\ProcessProductImage;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneProductImages extends Command
{
    protected $signature = 'products:prune-images';

    protected $description = 'Queue cleanup of abandoned uploads and retry pending image deletions';

    public function handle(): int
    {
        ProductImage::withTrashed()
            ->where(fn ($query) => $query->whereNotNull('deleted_at')->orWhere(fn ($query) => $query
                ->whereIn('status', ['pending', 'failed', 'processing'])->where('created_at', '<', now()->subHour())))
            ->chunkById(100, function ($images): void {
                foreach ($images as $image) {
                    DB::transaction(function () use ($image): void {
                        if (! $image->trashed() && $image->status === 'processing') {
                            ProcessProductImage::dispatch($image->id);

                            return;
                        }

                        if (! $image->trashed()) {
                            $image->delete();
                        }

                        DeleteProductImage::dispatch($image->id);
                    });
                }
            });

        $this->components->info('Image cleanup queued.');

        return self::SUCCESS;
    }
}
