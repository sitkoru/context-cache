<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;

class DirectProvider
{
    /**
     * @var DirectAdGroupsProvider
     */
    public $adGroups;
    /**
     * @var DirectAdsProvider
     */
    public $ads;
    /**
     * @var DirectCampaignsProvider
     */
    public $campaigns;
    /**
     * @var DirectKeywordsProvider
     */
    public $keywords;

    /**
     * YandexAdGroupsProvider constructor.
     * @param string          $accessToken
     * @param string          $clientLogin
     * @param ICacheProvider  $cacheProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        string $accessToken,
        string $clientLogin,
        ICacheProvider $cacheProvider,
        LoggerInterface $logger
    ) {
        $yandexService = new DirectApiService($accessToken, $clientLogin);

        $this->adGroups = new DirectAdGroupsProvider($yandexService, $cacheProvider, $logger);
        $this->ads = new DirectAdsProvider($yandexService, $cacheProvider, $logger);
        $this->keywords = new DirectKeywordsProvider($yandexService, $cacheProvider, $logger);
        $this->campaigns = new DirectCampaignsProvider($yandexService, $cacheProvider, $logger);


    }
}