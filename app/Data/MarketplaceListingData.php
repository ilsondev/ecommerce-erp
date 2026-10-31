<?php

namespace App\Data;

readonly class MarketplaceListingData
{
    /** @param array<int, string> $imageUrls */
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $sku,
        public string $price,
        public string $currency,
        public int $availableQuantity,
        public array $imageUrls,
    ) {}
}
