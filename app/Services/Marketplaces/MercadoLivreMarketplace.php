<?php

namespace App\Services\Marketplaces;

use App\Contracts\Marketplace;
use App\Data\MarketplaceListingData;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SensitiveParameter;
use UnexpectedValueException;

class MercadoLivreMarketplace implements Marketplace
{
    public function getListing(string $externalId, #[SensitiveParameter] string $accessToken): MarketplaceListingData
    {
        if (! preg_match('/^ML[A-Z][0-9]+$/', $externalId) || trim($accessToken) === '') {
            throw new InvalidArgumentException('Informe um anúncio e um token válidos.');
        }

        $listing = Http::baseUrl('https://api.mercadolibre.com')
            ->acceptJson()->withToken($accessToken)->connectTimeout(5)->timeout(15)
            ->get('/items/'.$externalId, ['include_attributes' => 'all'])->throw()->json();

        if (! is_array($listing) || ! isset($listing['id'], $listing['title'], $listing['price'], $listing['currency_id'], $listing['available_quantity'])) {
            throw new UnexpectedValueException('O marketplace retornou um anúncio incompleto.');
        }

        $sellerSku = $this->sellerSku($listing['attributes'] ?? null);

        return new MarketplaceListingData(
            externalId: $listing['id'],
            title: $listing['title'],
            sku: $sellerSku,
            price: number_format((float) $listing['price'], 2, '.', ''),
            currency: $listing['currency_id'],
            availableQuantity: (int) $listing['available_quantity'],
            imageUrls: $this->imageUrls($listing['pictures'] ?? null),
        );
    }

    private function sellerSku(mixed $attributes): ?string
    {
        if (! is_array($attributes)) {
            return null;
        }

        foreach ($attributes as $attribute) {
            if (is_array($attribute) && ($attribute['id'] ?? null) === 'SELLER_SKU' && is_string($attribute['value_name'] ?? null)) {
                return $attribute['value_name'];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function imageUrls(mixed $pictures): array
    {
        $urls = [];

        if (! is_array($pictures)) {
            return $urls;
        }

        foreach ($pictures as $picture) {
            if (is_array($picture) && is_string($picture['secure_url'] ?? null) && str_starts_with($picture['secure_url'], 'https://')) {
                $urls[] = $picture['secure_url'];
            }
        }

        return $urls;
    }
}
