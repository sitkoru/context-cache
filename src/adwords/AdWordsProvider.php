<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201809\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201809\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201809\cm\AdGroupService;
use Google\AdsApi\AdWords\v201809\cm\CampaignService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\Auth\CredentialsLoader;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;

class AdWordsProvider
{

    /**
     * @var AdWordsAdGroupsProvider
     */
    public $adGroups;

    /**
     * @var AdWordsAdsProvider
     */
    public $ads;

    /**
     * @var AdWordsCampaignsProvider
     */
    public $campaigns;

    /**
     * @var AdWordsAdGroupCriterionsProvider
     */
    public $criterions;

    /**
     * @var string
     */
    private $oAuthFilePath;

    /**
     * @var int
     */
    private $customerId;
    /**
     * @var string
     */
    private $refreshToken;

    /**
     * GoogleProvider constructor.
     * @param int                   $customerId
     * @param string                $oAuthFilePath
     * @param null|string           $refreshToken
     * @param ICacheProvider        $cacheProvider
     * @param ContextEntitiesLogger $logger
     */
    public function __construct(
        int $customerId,
        string $oAuthFilePath,
        ?string $refreshToken = null,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {

        $this->customerId = $customerId;
        $this->refreshToken = $refreshToken;
        $this->oAuthFilePath = $oAuthFilePath;

        $services = new AdWordsServices();
        $session = $this->getSession();

        /**
         * @var CampaignService $campaignService
         */
        $campaignService = $services->get($session, CampaignService::class);
        $this->campaigns = new AdWordsCampaignsProvider($campaignService, $cacheProvider, $session, $logger);

        /**
         * @var AdGroupService $adGroupService
         */
        $adGroupService = $services->get($session, AdGroupService::class);
        $this->adGroups = new AdWordsAdGroupsProvider($adGroupService, $cacheProvider, $session, $logger);

        /**
         * @var AdGroupAdService $adGroupAdService
         */
        $adGroupAdService = $services->get($session, AdGroupAdService::class);
        $this->ads = new AdWordsAdsProvider($adGroupAdService, $cacheProvider, $session, $logger);

        /**
         * @var AdGroupCriterionService $adGroupCriterionService
         */
        $adGroupCriterionService = $services->get($session, AdGroupCriterionService::class);
        $this->criterions = new AdWordsAdGroupCriterionsProvider($adGroupCriterionService, $cacheProvider, $session,
            $logger);


    }

    private function getSession(): AdWordsSession
    {
        $builder = $this->getSessionBuilder();

        return $builder->build();
    }

    private function getSessionBuilder(): AdWordsSessionBuilder
    {

        $oAuth2Credential = $this->getOAuthCredentials();

        $builder = (new AdWordsSessionBuilder())
            ->fromFile($this->oAuthFilePath)
            ->withOAuth2Credential($oAuth2Credential)
            ->withClientCustomerId($this->customerId);

        return $builder;
    }

    public function getOAuthCredentials(): CredentialsLoader
    {
        $authFile = $this->oAuthFilePath;
        $oauthBuilder = (new OAuth2TokenBuilder())
            ->fromFile($authFile);
        if ($this->refreshToken) {
            $oauthBuilder->withRefreshToken($this->refreshToken);
        }

        $errLevel = error_reporting();
        error_reporting(E_ERROR);
        $loader = $oauthBuilder
            ->build();
        error_reporting($errLevel);
        return $loader;
    }
}