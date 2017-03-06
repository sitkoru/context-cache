<?php

namespace sitkoru\contextcache;

use sitkoru\contextcache\adwords\AdWordsProvider;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\direct\DirectProvider;

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

    public function getDirectProvider(string $accessToken, string $clientLogin): DirectProvider
    {
        return new DirectProvider($accessToken, $clientLogin, $this->cache);
    }

    public function getAdWordsProvider(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null
    ): AdWordsProvider {
        return new AdWordsProvider($customerId, $oAuthFilePath, $refreshToken, $this->cache);
    }
}