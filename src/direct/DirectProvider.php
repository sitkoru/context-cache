<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
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
     * @var
     */
    private $accessToken;
    /**
     * @var
     */
    private $clientLogin;

    private $yandexService;
    /**
     * @var ICacheProvider
     */
    private $cacheProvider;

    /**
     * YandexAdGroupsProvider constructor.
     * @param string         $accessToken
     * @param string         $clientLogin
     * @param ICacheProvider $cacheProvider
     */
    public function __construct(string $accessToken, string $clientLogin, ICacheProvider $cacheProvider)
    {

        $this->accessToken = $accessToken;
        $this->clientLogin = $clientLogin;
        $this->yandexService = new DirectApiService($accessToken, $clientLogin);
        $this->cacheProvider = $cacheProvider;

        $this->adGroups = new DirectAdGroupsProvider($this->yandexService, $this->cacheProvider);
        $this->ads = new DirectAdsProvider($this->yandexService, $this->cacheProvider);
        $this->keywords = new DirectKeywordsProvider($this->yandexService, $this->cacheProvider);
        $this->campaigns = new DirectCampaignsProvider($this->yandexService, $this->cacheProvider);

    }
}