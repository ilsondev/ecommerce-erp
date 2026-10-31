<?php

namespace App\Services\Marketplaces;

use App\Contracts\Marketplace;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class MarketplaceManager
{
    public function __construct(private Container $container, private Repository $config) {}

    public function driver(string $name): Marketplace
    {
        $driver = $this->config->get('marketplaces.drivers.'.$name);

        if (! is_string($driver) || ! is_a($driver, Marketplace::class, true)) {
            throw new InvalidArgumentException('Marketplace não registrado: '.$name);
        }

        return $this->container->make($driver);
    }
}
