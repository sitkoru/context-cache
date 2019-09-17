<?php

namespace sitkoru\contextcache;

use directapi\components\interfaces\IQueryLogger;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\adwords\AdWordsProvider;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\direct\DirectProvider;

class ContextEntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    private $cache;
    /**
     * @var ContextEntitiesLogger
     */
    private $logger;

    public function __construct(ICacheProvider $cacheProvider, ?LoggerInterface $logger)
    {
        $this->cache = $cacheProvider;
        $this->logger = new ContextEntitiesLogger($logger);
    }

    public function clearCache(): void
    {
        $collections = [
            'yandex' => [
                'ads',
                'adGroups',
                'campaigns',
                'keywords',
                'audiencetargets',
                'retargetinglists'
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
                $this->cache->collection($service, $serviceCollection, '')->clear();
            }
        }
    }


    public function getDirectProvider(
        string $accessToken,
        string $clientLogin,
        IQueryLogger $queryLogger = null,
        bool $useSandbox = false
    ): DirectProvider {
        return new DirectProvider($accessToken, $clientLogin, $this->cache, $this->logger, $queryLogger, $useSandbox);
    }

    public function getAdWordsProvider(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null
    ): AdWordsProvider {
        return new AdWordsProvider($customerId, $oAuthFilePath, $refreshToken, $this->cache, $this->logger);
    }
}
