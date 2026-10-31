<?php

use App\Contracts\Marketplace;
use App\Data\MarketplaceListingData;
use App\Services\Marketplaces\MarketplaceManager;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

test('the Mercado Livre adapter maps listings into marketplace independent data', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.mercadolibre.com/items/MLB123456*' => Http::response([
        'id' => 'MLB123456', 'title' => 'Produto de teste', 'price' => 129.9, 'currency_id' => 'BRL', 'available_quantity' => 7,
        'attributes' => [['id' => 'SELLER_SKU', 'value_name' => 'SKU-01']],
        'pictures' => [['secure_url' => 'https://images.example.test/product.jpg'], ['url' => 'http://insecure.test/image.jpg']],
    ])]);

    $listing = app(MarketplaceManager::class)->driver('mercado_livre')->getListing('MLB123456', 'seller-token');

    expect($listing)->toEqual(new MarketplaceListingData('MLB123456', 'Produto de teste', 'SKU-01', '129.90', 'BRL', 7, ['https://images.example.test/product.jpg']));
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer seller-token') && $request['include_attributes'] === 'all');
});

test('new marketplace drivers can be registered without editing the catalog', function () {
    $driver = new class implements Marketplace
    {
        public function getListing(string $externalId, string $accessToken): MarketplaceListingData
        {
            return new MarketplaceListingData($externalId, 'Outro marketplace', null, '10.00', 'BRL', 3, []);
        }
    };
    config(['marketplaces.drivers.other' => $driver::class]);
    $this->app->instance($driver::class, $driver);

    $listing = app(MarketplaceManager::class)->driver('other')->getListing('OTHER-01', 'token');

    expect($listing->title)->toBe('Outro marketplace');
});

test('unregistered marketplace drivers fail explicitly', function () {
    app(MarketplaceManager::class)->driver('missing');
})->throws(InvalidArgumentException::class, 'Marketplace não registrado: missing');

test('invalid marketplace identifiers never make HTTP requests', function (string $externalId, string $token) {
    Http::preventStrayRequests();
    Http::fake();

    expect(fn () => app(MarketplaceManager::class)->driver('mercado_livre')->getListing($externalId, $token))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with(['invalid identifier' => ['../../users/me', 'token'], 'missing token' => ['MLB123456', '']]);

test('marketplace API failures are never reported as successful listings', function (int $status) {
    Http::preventStrayRequests();
    Http::fake(['https://api.mercadolibre.com/items/MLB123456*' => Http::response(['message' => 'unavailable'], $status)]);

    expect(fn () => app(MarketplaceManager::class)->driver('mercado_livre')->getListing('MLB123456', 'token'))->toThrow(RequestException::class);

    Http::assertSentCount(1);
})->with(['expired token' => 401, 'rate limit' => 429, 'server failure' => 503]);

test('incomplete marketplace responses fail explicitly', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.mercadolibre.com/items/MLB123456*' => Http::response(['id' => 'MLB123456'])]);

    expect(fn () => app(MarketplaceManager::class)->driver('mercado_livre')->getListing('MLB123456', 'token'))->toThrow(UnexpectedValueException::class);

    Http::assertSentCount(1);
});
