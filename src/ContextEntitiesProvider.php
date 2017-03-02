<?php

namespace sitkoru\contextcache;


use sitkoru\contextcache\common\MongoDbCacheProvider;
use sitkoru\contextcache\google\GoogleProvider;
use sitkoru\contextcache\yandex\YandexProvider;

class ContextEntitiesProvider
{
    private $cache;

    public function __construct(string $mongoDbUrl)
    {
        $this->cache = new MongoDbCacheProvider($mongoDbUrl);
    }

    public function getYandexProvider(string $accessToken, string $clientLogin): YandexProvider
    {
        return new YandexProvider($accessToken, $clientLogin, $this->cache);
    }

    public function getGoogleProvider(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null
    ): GoogleProvider {
        return new GoogleProvider($customerId, $oAuthFilePath, $refreshToken, $this->cache);
    }
}