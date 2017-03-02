<?php

namespace sitkoru\contextcache\yandex;


use directapi\DirectApiService;
use sitkoru\contextcache\common\ICacheProvider;

class YandexProvider
{
    /**
     * @var YandexAdGroupsProvider
     */
    public $adGroups;
    /**
     * @var YandexAdsProvider
     */
    public $ads;
    /**
     * @var YandexCampaignsProvider
     */
    public $campaigns;
    /**
     * @var YandexKeywordsProvider
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

        $this->adGroups = new YandexAdGroupsProvider($this->yandexService, $this->cacheProvider);
        $this->ads = new YandexAdsProvider($this->yandexService, $this->cacheProvider);
        $this->keywords = new YandexKeywordsProvider($this->yandexService, $this->cacheProvider);
        $this->campaigns = new YandexCampaignsProvider($this->yandexService, $this->cacheProvider);

    }
}