<?php

namespace sitkoru\contextcache;

use directapi\components\interfaces\IQueryLogger;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\adwords\AdWordsProvider;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\direct\DirectProvider;

class ContextEntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    private $cache;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ICacheProvider $cacheProvider, LoggerInterface $logger)
    {
        $this->cache = $cacheProvider;
        $this->logger = $logger;
    }

    public function clearCache()
    {
        $collections = [
            'yandex' => [
                'ads',
                'adGroups',
                'campaigns',
                'keywords'
            ],
            'google' => [
                'adGroupCriterions',
                'adGroups',
                'adGroupAds',
                'campaigns'
            ]
        ];
        foreach ($collections as $service => $serviceCollections) {
            foreach ($serviceCollections as $serviceCollection) {
                $this->cache->collection($service, $serviceCollection)->clear();
            }
        }
    }


    public function getDirectProvider(string $accessToken, string $clientLogin, IQueryLogger $queryLogger = null): DirectProvider
    {
        return new DirectProvider($accessToken, $clientLogin, $this->cache, $this->logger, $queryLogger);
    }

    public function getAdWordsProvider(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null
    ): AdWordsProvider {
        return new AdWordsProvider($customerId, $oAuthFilePath, $refreshToken, $this->cache, $this->logger);
    }
}