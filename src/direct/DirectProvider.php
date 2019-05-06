<?php

namespace sitkoru\contextcache\direct;


use directapi\components\interfaces\IQueryLogger;
use directapi\DirectApiService;
use sitkoru\contextcache\common\ContextEntitiesLogger;
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
     * @var DirectRetargetingProvider
     */
    public $retargetingLists;

    /**
     * @var DirectAudienceTargetsProvider
     */
    public $audienceTargetsProvider;


    /**
     * YandexAdGroupsProvider constructor.
     * @param string                $accessToken
     * @param string                $clientLogin
     * @param ICacheProvider        $cacheProvider
     * @param ContextEntitiesLogger $logger
     * @param null|IQueryLogger     $queryLogger
     * @param bool                  $useSandbox
     */
    public function __construct(
        string $accessToken,
        string $clientLogin,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger,
        ?IQueryLogger $queryLogger = null,
        $useSandbox = false
    ) {
        $yandexService = new DirectApiService($accessToken, $clientLogin, $queryLogger, $logger->getLogger(), $useSandbox);

        $this->adGroups = new DirectAdGroupsProvider($yandexService, $cacheProvider, $logger);
        $this->ads = new DirectAdsProvider($yandexService, $cacheProvider, $logger);
        $this->keywords = new DirectKeywordsProvider($yandexService, $cacheProvider, $logger);
        $this->campaigns = new DirectCampaignsProvider($yandexService, $cacheProvider, $logger);
        $this->retargetingLists = new DirectRetargetingProvider($yandexService, $cacheProvider, $logger);
        $this->audienceTargetsProvider = new DirectAudienceTargetsProvider($yandexService, $cacheProvider, $logger);


    }
}