<?php

namespace App\Contracts;

use App\Data\MarketplaceListingData;
use SensitiveParameter;

interface Marketplace
{
    public function getListing(string $externalId, #[SensitiveParameter] string $accessToken): MarketplaceListingData;
}
