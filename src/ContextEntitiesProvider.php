<?php

namespace sitkoru\contextcache;


use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\google\GoogleProvider;
use sitkoru\contextcache\yandex\YandexProvider;

class ContextEntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    private $cache;

    public function __construct(ICacheProvider $cacheProvider)
    {
        $this->cache = $cacheProvider;
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